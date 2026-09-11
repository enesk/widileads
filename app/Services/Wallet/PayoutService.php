<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\PayoutStatus;
use App\Constants\WalletTransactionType;
use App\Exceptions\PayoutNotAllowedException;
use App\Mail\Wallet\PayoutProcessedMail;
use App\Mail\Wallet\PayoutRequestedMail;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SupportMailbox;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auszahlungen an den Verkaeufer: anfordern, ueberweisen, ablehnen
 * (LP-WALLET-010).
 *
 * Ueberwiesen wird von Hand -- es gibt keine Bankanbindung, Enes zahlt aus und
 * markiert die Anforderung als erledigt. Dieser Dienst macht die Geldseite
 * dazu, und zwar ueber WalletService::post() wie jede andere Buchung des
 * Marktplatzes.
 *
 * **Abgebucht wird sofort bei der Anforderung.** Der Betrag verlaesst das
 * Wallet in dem Moment, in dem der Verkaeufer ihn anfordert, nicht erst bei der
 * Ueberweisung. Sonst koennte derselbe Saldo zweimal angefordert werden --
 * zwischen Anforderung und Ueberweisung liegen Tage, und die zweite Anforderung
 * saehe den unveraenderten Saldo. Eine Ablehnung bucht mit einer
 * `adjustment`-Zeile zurueck; die urspruengliche `payout`-Zeile bleibt stehen,
 * denn ein Journal wird nicht korrigiert, sondern gegengebucht.
 *
 * **Wiederholung ist harmlos.** Eine Anforderung, die schon im Zielzustand
 * steht, wird unveraendert zurueckgegeben; jede Buchung traegt einen
 * Idempotenzschluessel nach WalletService::keyFor(). Eine Entscheidung ueber
 * eine bereits entschiedene Anforderung dagegen bricht mit
 * PayoutNotAllowedException ab -- das ist ein Fehler im Aufrufer.
 *
 * **Die Anforderung wird gesperrt gelesen.** Zustandspruefung, Buchung und
 * Zustandswechsel laufen unter `lockForUpdate()` auf der Zeile, damit
 * Ueberweisung und Ablehnung derselben Anforderung nicht gleichzeitig die
 * Pruefung passieren.
 *
 * Abweichung von der Ticketvorgabe: `request()` bekommt einen Tenant und keinen
 * "Seller" -- es gibt im Funnel Builder keine `sellers`-Tabelle, Verkaeufer
 * sind Mandanten (LP-WALLET-003).
 */
class PayoutService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly SupportMailbox $support,
    ) {}

    /**
     * Auszahlung anfordern: Betrag sofort vom Verkaeufer-Wallet abbuchen und
     * den Beleg anlegen.
     *
     * Geprueft wird gegen `available_cents` und nicht gegen `balance_cents` wie
     * im Ticket beschrieben. Fuer Verkaeufer-Wallets ist beides derselbe Wert
     * -- reserviert wird nur beim Kaeufer --, die strengere Pruefung kostet
     * also nichts und bleibt auch dann richtig, wenn spaeter einmal auf einem
     * Verkaeufer-Wallet etwas geblockt wird.
     *
     * Beleg und Buchung entstehen in einer Transaktion. Ein Beleg ohne Buchung
     * waere eine Auszahlung, der kein Geld gegenuebersteht.
     *
     * @param  User|null  $requestedBy  Der Benutzer, der angefordert hat; Empfaenger der spaeteren Entscheidungsmail
     *
     * @throws PayoutNotAllowedException wenn keine IBAN hinterlegt ist, der Betrag unter dem Mindestbetrag liegt oder das Guthaben nicht reicht
     */
    public function request(Tenant $seller, int $amountCents, ?User $requestedBy = null): PayoutRequest
    {
        if (! $seller->hasPayoutIban()) {
            throw PayoutNotAllowedException::missingIban();
        }

        $minimumCents = (int) config('wallet.payout_min_cents');

        if ($amountCents < $minimumCents) {
            throw PayoutNotAllowedException::belowMinimum($amountCents, $minimumCents);
        }

        $wallet = Wallet::forSeller($seller);

        if ($amountCents > $wallet->available_cents) {
            throw PayoutNotAllowedException::exceedsBalance($amountCents, $wallet->available_cents);
        }

        $payout = DB::transaction(function () use ($seller, $wallet, $amountCents, $requestedBy): PayoutRequest {
            $payout = PayoutRequest::query()->create([
                'wallet_id' => $wallet->getKey(),
                'amount_cents' => $amountCents,
                'iban_last4' => $seller->payout_iban_last4,
                'status' => PayoutStatus::REQUESTED,
                'requested_at' => now(),
            ]);

            $this->wallets->post(
                wallet: $wallet,
                type: WalletTransactionType::PAYOUT,
                amountCents: -$amountCents,
                description: __('marketplace.wallet.descriptions.payout', ['payout' => $payout->getKey()]),
                reference: $payout,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::PAYOUT, $payout),
                meta: $this->metaFor($payout),
                createdBy: $requestedBy?->getKey() === null ? null : (int) $requestedBy->getKey(),
            );

            return $payout;
        });

        $this->notifyOperator($payout, $seller);

        return $payout;
    }

    /**
     * Ueberwiesen: Die Anforderung ist erledigt. Es wird nichts mehr gebucht --
     * das Geld hat das Wallet mit der Anforderung verlassen.
     *
     * @throws PayoutNotAllowedException wenn ueber die Anforderung schon entschieden ist
     */
    public function markPaid(PayoutRequest $request, User $admin, ?string $note = null): PayoutRequest
    {
        return $this->decide($request, PayoutStatus::PAID, $admin, $note, null);
    }

    /**
     * Abgelehnt: Der Betrag geht als Korrekturbuchung ins Wallet zurueck.
     *
     * Als `adjustment` und nicht als Rueckbuchung eines `payout`: Die
     * Buchungsart `payout` traegt immer ein Minus (WalletTransactionType), Geld
     * verlaesst das Wallet. Was hier passiert, ist eine Korrektur durch den
     * Admin, und genau so steht es auch im Journal -- mit Grund und Verursacher.
     *
     * @throws PayoutNotAllowedException wenn ueber die Anforderung schon entschieden ist
     */
    public function reject(PayoutRequest $request, User $admin, string $note): PayoutRequest
    {
        return $this->decide($request, PayoutStatus::REJECTED, $admin, $note, function (PayoutRequest $locked) use ($admin, $note): void {
            $this->wallets->post(
                wallet: $locked->wallet,
                type: WalletTransactionType::ADJUSTMENT,
                amountCents: $locked->amount_cents,
                description: __('marketplace.wallet.descriptions.payout_rejected', ['payout' => $locked->getKey()]),
                reference: $locked,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::ADJUSTMENT, $locked, 'reject'),
                meta: $this->metaFor($locked) + [
                    'reason' => $note,
                    'rejected_by' => (int) $admin->getKey(),
                ],
                createdBy: (int) $admin->getKey(),
            );
        });
    }

    /**
     * Der auszahlbare Betrag eines Verkaeufers: sein freies Guthaben, sofern es
     * den Mindestbetrag erreicht. Grundlage der Schaltflaeche im
     * Verkaeufer-Portal (LP-WALLET-012).
     */
    public function payableCents(Tenant $seller): int
    {
        $available = Wallet::forSeller($seller)->available_cents;

        return $available < (int) config('wallet.payout_min_cents') ? 0 : $available;
    }

    /**
     * IBAN in der Form, in der sie gespeichert wird: ohne Leerzeichen und in
     * Grossbuchstaben. Eine IBAN mit Leerzeichen waere dieselbe Nummer, wuerde
     * aber eine andere letzte Vierergruppe liefern.
     */
    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    /**
     * Entscheidung ueber eine offene Anforderung, samt ihren Buchungen.
     *
     * Die Anforderung wird gesperrt neu geladen, weil zwischen Aufruf und
     * Entscheidung ein anderer Prozess dieselbe Anforderung entschieden haben
     * kann. Steht sie dann schon im Zielzustand, ist der Aufruf eine
     * Wiederholung und gibt den bestehenden Stand zurueck.
     *
     * @param  Closure(PayoutRequest): void|null  $book
     *
     * @throws PayoutNotAllowedException
     */
    private function decide(
        PayoutRequest $request,
        PayoutStatus $target,
        User $admin,
        ?string $note,
        ?Closure $book,
    ): PayoutRequest {
        if ($request->status === $target) {
            return $request;
        }

        $decided = DB::transaction(function () use ($request, $target, $admin, $note, $book): bool {
            $locked = PayoutRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === $target) {
                $request->setRawAttributes($locked->getAttributes(), true);

                return false;
            }

            if (! $locked->status->isOpen()) {
                throw PayoutNotAllowedException::alreadyProcessed($locked, $target);
            }

            if ($book !== null) {
                $book($locked);
            }

            $locked->forceFill([
                'status' => $target,
                'processed_at' => now(),
                'processed_by' => (int) $admin->getKey(),
                // Eine leere Notiz ueberschreibt keine vorhandene: bei der
                // Ueberweisung ist sie freiwillig.
                'note' => $note === null || $note === '' ? $locked->note : $note,
            ])->save();

            $request->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($decided) {
            $this->notifySeller($request);
        }

        return $request;
    }

    /**
     * Meldet die neue Anforderung an die eigene Support-Adresse.
     *
     * Ausgezahlt wird von Hand -- ohne diese Meldung bliebe die Anforderung
     * liegen, bis jemand von sich aus in die Liste schaut. Ein stummer
     * Mailserver darf die Anforderung aber nicht nachtraeglich scheitern
     * lassen: Der Betrag ist zu diesem Zeitpunkt bereits gebucht.
     */
    private function notifyOperator(PayoutRequest $payout, Tenant $seller): void
    {
        $recipient = $this->support->addressOrLog(
            'Auszahlungsanforderung ohne Support-Meldung: app.support_email ist nicht brauchbar gesetzt.',
            ['payout_request_id' => $payout->getKey()],
        );

        if ($recipient === null) {
            return;
        }

        $this->send($payout, fn () => Mail::to($recipient)->send(new PayoutRequestedMail($payout, $seller)));
    }

    /**
     * Meldet die Entscheidung dem Verkaeufer. Empfaenger ist ein Benutzer des
     * Mandanten -- die Auszahlung gilt dem Mandanten, nicht einer Person.
     */
    private function notifySeller(PayoutRequest $payout): void
    {
        $seller = $payout->wallet?->owner;

        if (! $seller instanceof Tenant) {
            return;
        }

        $recipient = $seller->users()->first();

        if (! $recipient instanceof User || ! is_string($recipient->email) || $recipient->email === '') {
            return;
        }

        $this->send($payout, fn () => Mail::to($recipient->email)->send(new PayoutProcessedMail($payout, $seller)));
    }

    /**
     * Versand, der den Vorgang nicht mitreisst. Mit QUEUE_CONNECTION=sync
     * laeuft der Mailversand im Request mit; die Geldbewegung ist zu diesem
     * Zeitpunkt festgeschrieben und darf an einem Mailserver nicht scheitern.
     *
     * @param  Closure(): mixed  $send
     */
    private function send(PayoutRequest $payout, Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::warning('Meldung zur Auszahlung konnte nicht verschickt werden.', [
                'payout_request_id' => $payout->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Angaben, die jede Buchung einer Auszahlung mitfuehrt.
     *
     * @return array<string, mixed>
     */
    private function metaFor(PayoutRequest $payout): array
    {
        return [
            'payout_request_id' => (int) $payout->getKey(),
            'amount_cents' => (int) $payout->amount_cents,
            'iban_last4' => $payout->iban_last4,
        ];
    }
}
