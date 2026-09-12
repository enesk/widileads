<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\AuditAction;
use App\Constants\PaymentMode;
use App\Constants\PurchaseStatus;
use App\Constants\SettlementStatus;
use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Events\Postpaid\PostpaidDowngraded;
use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Exceptions\PostpaidNotAllowedException;
use App\Mail\Wallet\PostpaidApplicationReceivedMail;
use App\Mail\Wallet\PostpaidApprovedMail;
use App\Mail\Wallet\PostpaidDowngradedMail;
use App\Mail\Wallet\PostpaidRejectedMail;
use App\Models\LeadPurchase;
use App\Models\PostpaidApplication;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AuditLogger;
use App\Services\SupportMailbox;
use App\Support\EligibilityResult;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Eignung, Antrag und Freischaltung von Pay as you go (LP-POSTPAID-006).
 *
 * Die Freischaltung ist bewusst zweistufig: Die Eignungspruefung ist ein
 * **Vorfilter**, keine Entscheidung. Sie haelt fern, wer die Huerden aus
 * config('wallet.postpaid.*') nicht nimmt; freigegeben wird von Hand, weil am
 * Ende ein Forderungsrisiko steht, das keine Kennzahl abschliessend beurteilt.
 * Der Admin sieht dazu den Schnappschuss der Zahlen, die beim Antrag galten
 * (LP-PAYG-011).
 *
 * Dieser Dienst ist die einzige Stelle, die `wallets.payment_mode`,
 * `wallets.credit_limit_cents` und die Belegspalten der Freischaltung schreibt
 * und die einzige, die `postpaid_applications` fuehrt. Ein Kreditrahmen ist
 * dabei keine Buchung, sondern eine Erlaubnis (Wallet::$available_cents) --
 * Freischaltung und Rahmenaenderung bewegen kein Geld. Die einzige Ausnahme
 * ist die Gebuehr bei der Rueckstufung (LP-POSTPAID-009), und auch sie laeuft
 * ueber den WalletService wie jede andere Buchung.
 *
 * Der Hauptschalter `wallet.postpaid.enabled` blockiert nur den Antrag. Eine
 * bereits erteilte Freigabe wird durch ihn nicht widerrufen -- das waere eine
 * Rueckstufung, und die hat ihren eigenen Weg (LP-POSTPAID-009).
 */
class PostpaidService
{
    /**
     * Rueckstufungsgruende, die eine Zahlungsstoerung belegen. Wer sie in
     * `wallets.postpaid_disabled_reason` stehen hat, gilt fuer
     * `clean_history_days` als ungeeignet -- unabhaengig davon, was das
     * Journal sagt: Der gescheiterte Einzug kann laengst ausgeglichen sein,
     * die Stoerung bleibt trotzdem eine.
     *
     * @var list<string>
     */
    public const DISQUALIFYING_DISABLE_REASONS = ['settlement_failed', 'sepa_return', 'chargeback'];

    public function __construct(
        private readonly SupportMailbox $support,
        private readonly AuditLogger $audit,
        private readonly WalletService $wallets,
    ) {}

    /**
     * Prueft, ob ein Kauf-Wallet die Voraussetzungen fuer Pay as you go
     * erfuellt.
     *
     * Geprueft werden vier Dinge: genug abgerechnete Kaeufe, Mindestalter des
     * Kontos, eine Zahlungshistorie ohne Stoerung im Beobachtungszeitraum und
     * keine laufende Kaufsperre. Jede nicht erfuellte Regel liefert einen
     * deutschen Satz; das Zahlenmaterial wandert unveraendert in den
     * Schnappschuss.
     *
     * Die Methode wirft nicht: Sie ist eine Auskunft und wird auch dann
     * aufgerufen, wenn der Kaeufer offensichtlich nicht in Frage kommt.
     */
    public function eligibility(Wallet $wallet): EligibilityResult
    {
        $buyer = $wallet->owner;
        $now = now();

        $cleanHistoryDays = (int) config('wallet.postpaid.clean_history_days');
        $minPurchases = (int) config('wallet.postpaid.min_captured_purchases');
        $minAccountAgeDays = (int) config('wallet.postpaid.min_account_age_days');
        $since = $now->copy()->subDays($cleanHistoryDays);

        $capturedPurchases = $buyer instanceof Tenant ? $this->capturedPurchases($buyer) : 0;
        $accountAgeDays = $buyer instanceof Tenant ? $this->accountAgeDays($buyer) : 0;
        $failedSettlements = $this->failedSettlements($wallet, $since);
        $chargebacks = $this->chargebacks($wallet, $since);
        $disablingReason = $this->disqualifyingDisableReason($wallet, $since);

        $snapshot = [
            'checked_at' => $now->toIso8601String(),
            'wallet_id' => (int) $wallet->getKey(),
            'tenant_id' => $buyer?->getKey(),
            'payment_mode' => $wallet->payment_mode->value,
            'balance_cents' => (int) $wallet->balance_cents,
            'open_amount_cents' => $wallet->open_amount_cents,
            'purchase_blocked' => (bool) $wallet->purchase_blocked,
            'captured_purchases' => $capturedPurchases,
            'min_captured_purchases' => $minPurchases,
            'account_age_days' => $accountAgeDays,
            'min_account_age_days' => $minAccountAgeDays,
            'clean_history_days' => $cleanHistoryDays,
            'failed_settlements' => $failedSettlements,
            'chargebacks' => $chargebacks,
            'postpaid_disabled_reason' => $wallet->postpaid_disabled_reason,
            'postpaid_disabled_at' => $wallet->postpaid_disabled_at?->toIso8601String(),
        ];

        $reasons = [];

        if ($buyer === null) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.no_tenant');
        }

        if ($capturedPurchases < $minPurchases) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.purchases', [
                'count' => (string) $capturedPurchases,
                'required' => (string) $minPurchases,
            ]);
        }

        if ($accountAgeDays < $minAccountAgeDays) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.account_age', [
                'days' => (string) $accountAgeDays,
                'required' => (string) $minAccountAgeDays,
            ]);
        }

        if ($failedSettlements > 0 || $chargebacks > 0) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.payment_history', [
                'days' => (string) $cleanHistoryDays,
            ]);
        }

        if ($disablingReason !== null) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.previous_downgrade', [
                'days' => (string) $cleanHistoryDays,
            ]);
        }

        if ($wallet->purchase_blocked) {
            $reasons[] = __('marketplace.wallet.postpaid.eligibility.reasons.purchase_blocked');
        }

        return EligibilityResult::fromReasons($reasons, $snapshot);
    }

    /**
     * Stellt den Antrag auf Freischaltung und meldet ihn dem Betreiber.
     *
     * Der Antrag ist der Beleg der spaeteren Entscheidung: Der Schnappschuss
     * der Eignungszahlen wird hier festgeschrieben und nie wieder angefasst.
     *
     * @param  User|null  $user  Der antragstellende Benutzer, fuer die Meldung an den Betreiber
     *
     * @throws PostpaidNotAllowedException wenn das Verfahren abgeschaltet ist (403), der Kaeufer nicht geeignet ist, kein Standard-Zahlungsmittel hinterlegt hat, bereits einen offenen Antrag hat oder die Sperrfrist nach einer Ablehnung laeuft
     */
    public function apply(Wallet $wallet, ?User $user = null): PostpaidApplication
    {
        if (! $this->isEnabled()) {
            throw PostpaidNotAllowedException::featureDisabled();
        }

        $this->guardBuyerWallet($wallet);

        if ($wallet->isPostpaid()) {
            throw PostpaidNotAllowedException::alreadyEnabled();
        }

        if ($wallet->postpaidApplications()->pending()->exists()) {
            throw PostpaidNotAllowedException::applicationPending();
        }

        $blockedDays = $this->reapplyBlockedDays($wallet);

        if ($blockedDays > 0) {
            throw PostpaidNotAllowedException::rejectedRecently($blockedDays);
        }

        if ($wallet->defaultPaymentMethod()->doesntExist()) {
            throw PostpaidNotAllowedException::paymentMethodRequired();
        }

        $eligibility = $this->eligibility($wallet);

        if (! $eligibility->eligible) {
            throw PostpaidNotAllowedException::notEligible($eligibility);
        }

        $application = PostpaidApplication::query()->create([
            'wallet_id' => $wallet->getKey(),
            'status' => PostpaidApplication::STATUS_REQUESTED,
            'eligibility_snapshot' => $eligibility->toArray(),
            'requested_at' => now(),
        ]);

        $this->notifyOperator($application, $wallet, $user);

        return $application;
    }

    /**
     * Gibt den Antrag frei: Das Wallet kauft ab sofort gegen Kreditrahmen.
     *
     * Der Rahmen ist der Betrag, um den der Saldo ins Minus laufen darf; ohne
     * ausdrueckliche Angabe gilt config('wallet.postpaid.default_credit_limit_cents').
     * Eine fruehere Rueckstufung wird dabei geloescht -- sie waere sonst der
     * Grund, aus dem der eben Freigeschaltete wieder als ungeeignet gilt.
     *
     * Wiederholung ist harmlos: Ein bereits freigegebener Antrag wird
     * unveraendert zurueckgegeben.
     *
     * @throws PostpaidNotAllowedException wenn ueber den Antrag schon anders entschieden wurde oder der Rahmen negativ ist
     */
    public function approve(PostpaidApplication $application, User $admin, ?int $creditLimitCents = null): PostpaidApplication
    {
        $limit = $creditLimitCents ?? (int) config('wallet.postpaid.default_credit_limit_cents');

        if ($limit < 0) {
            throw PostpaidNotAllowedException::invalidCreditLimit($limit);
        }

        $decided = $this->decide(
            $application,
            PostpaidApplication::STATUS_APPROVED,
            $admin,
            null,
            function (PostpaidApplication $locked) use ($admin, $limit): void {
                $wallet = $locked->wallet;

                $this->guardBuyerWallet($wallet);

                $wallet->forceFill([
                    'payment_mode' => PaymentMode::POSTPAID,
                    'credit_limit_cents' => $limit,
                    'postpaid_enabled_at' => now(),
                    'postpaid_enabled_by' => (int) $admin->getKey(),
                    'postpaid_disabled_at' => null,
                    'postpaid_disabled_reason' => null,
                ])->save();
            },
        );

        if ($decided) {
            $this->audit->log(
                action: AuditAction::POSTPAID_APPROVED,
                subject: $application,
                payload: [
                    'wallet_id' => (int) $application->wallet_id,
                    'credit_limit_cents' => $limit,
                ],
                tenant: $application->wallet?->owner,
                user: $admin,
            );

            $this->notifyBuyer($application, fn (Tenant $buyer, string $email) => Mail::to($email)
                ->send(new PostpaidApprovedMail($application, $buyer, $limit)));
        }

        return $application;
    }

    /**
     * Lehnt den Antrag ab. Die Begruendung bleibt intern: Sie steht am Beleg
     * und im Admin, nicht in der Mail.
     *
     * Warum ohne Detailbegruendung: Wer erfaehrt, an welcher Zahl es lag,
     * kann genau diese Zahl herstellen. Der Kaeufer erfaehrt stattdessen,
     * wann er wieder beantragen darf.
     *
     * @throws PostpaidNotAllowedException wenn ueber den Antrag schon anders entschieden wurde
     */
    public function reject(PostpaidApplication $application, User $admin, string $note): PostpaidApplication
    {
        $decided = $this->decide($application, PostpaidApplication::STATUS_REJECTED, $admin, $note, null);

        if ($decided) {
            $this->audit->log(
                action: AuditAction::POSTPAID_REJECTED,
                subject: $application,
                payload: [
                    'wallet_id' => (int) $application->wallet_id,
                    'note' => $note,
                ],
                tenant: $application->wallet?->owner,
                user: $admin,
            );

            $this->notifyBuyer($application, fn (Tenant $buyer, string $email) => Mail::to($email)
                ->send(new PostpaidRejectedMail($application, $buyer)));
        }

        return $application;
    }

    /**
     * Aendert den Kreditrahmen eines freigeschalteten Wallets.
     *
     * Gebucht wird dabei nichts: Der Rahmen ist eine Erlaubnis und kein
     * Guthaben (Wallet::$available_cents). Im Journal taucht er deshalb nicht
     * auf -- nachvollziehbar bleibt die Aenderung ueber das Audit-Log, mit
     * altem und neuem Wert und dem Admin, der sie vorgenommen hat.
     *
     * Der Rahmen darf unter den bereits ausgeschoepften Betrag gesenkt werden:
     * Das ist der Regelfall beim Zurueckdrehen eines zu grossen Rahmens. Der
     * Kaeufer kann dann nichts mehr kaufen, schuldet aber unveraendert, was er
     * schuldet.
     *
     * @throws PostpaidNotAllowedException wenn das Wallet kein freigeschaltetes Kauf-Wallet ist oder der Rahmen negativ waere
     */
    public function updateCreditLimit(Wallet $wallet, int $cents, User $admin): Wallet
    {
        $this->guardBuyerWallet($wallet);

        if (! $wallet->isPostpaid()) {
            throw PostpaidNotAllowedException::notPostpaid();
        }

        if ($cents < 0) {
            throw PostpaidNotAllowedException::invalidCreditLimit($cents);
        }

        $previous = (int) $wallet->credit_limit_cents;

        if ($previous === $cents) {
            return $wallet;
        }

        $wallet->forceFill(['credit_limit_cents' => $cents])->save();

        $this->audit->log(
            action: AuditAction::POSTPAID_CREDIT_LIMIT_CHANGED,
            subject: $wallet,
            payload: [
                'wallet_id' => (int) $wallet->getKey(),
                'previous_credit_limit_cents' => $previous,
                'credit_limit_cents' => $cents,
            ],
            tenant: $wallet->owner,
            user: $admin,
        );

        return $wallet;
    }

    /**
     * Schaltet Pay as you go nach einer Rueckstufung wieder frei
     * (LP-POSTPAID-012).
     *
     * Eine Entscheidung des Betreibers und keine Auskunft: Die Eignung wird
     * hier bewusst nicht erneut verlangt -- nach einer Rueckstufung ist sie
     * per Definition nicht gegeben (DISQUALIFYING_DISABLE_REASONS), und genau
     * darueber setzt sich der Admin mit dieser Handlung hinweg. Der
     * Schnappschuss der Zahlen wird trotzdem festgehalten, damit spaeter
     * nachvollziehbar bleibt, worueber entschieden wurde.
     *
     * Der Weg fuehrt ueber einen Antrag und nicht an ihm vorbei: So entsteht
     * derselbe Beleg, dieselbe Mail an den Kaeufer und derselbe Audit-Eintrag
     * wie bei einer regulaeren Freigabe. Liegt bereits ein offener Antrag vor,
     * wird dieser entschieden statt eines zweiten angelegt.
     *
     * @throws PostpaidNotAllowedException wenn das Wallet kein Kauf-Wallet ist, bereits auf Postpaid steht oder der Rahmen negativ waere
     */
    public function reenable(Wallet $wallet, User $admin, ?int $creditLimitCents = null): PostpaidApplication
    {
        $this->guardBuyerWallet($wallet);

        if ($wallet->isPostpaid()) {
            throw PostpaidNotAllowedException::alreadyEnabled();
        }

        $application = $this->pendingApplication($wallet) ?? PostpaidApplication::query()->create([
            'wallet_id' => $wallet->getKey(),
            'status' => PostpaidApplication::STATUS_REQUESTED,
            'eligibility_snapshot' => $this->eligibility($wallet)->toArray(),
            'requested_at' => now(),
            'note' => __('marketplace.wallet.admin.postpaid.reenable_note'),
        ]);

        $approved = $this->approve($application, $admin, $creditLimitCents);

        $wallet->refresh();

        return $approved;
    }

    /**
     * Beendet Pay as you go nach einer Zahlungsstoerung (LP-POSTPAID-009).
     *
     * Bewusst hart und ohne Ermessen: Beim ersten echten Zahlungsproblem ist
     * das Verfahren vorbei. Das ist die zentrale Risikobremse -- Inkasso lohnt
     * bei diesen Betraegen nicht, also darf der Ausfall gar nicht erst
     * wachsen.
     *
     * Vier Dinge passieren, in dieser Reihenfolge:
     *
     * 1. Der Zustand des Wallets: Modus zurueck auf Vorauszahlung, Rahmen auf
     *    0, Grund und Zeitpunkt als Beleg.
     * 2. Offene Einzuege (`pending`, `retry_pending`) werden auf `failed`
     *    gesetzt. Nach der Rueckstufung darf kein automatischer Versuch mehr
     *    laufen -- der stuendliche Lauf von `wallet:settle --charge-only`
     *    wuerde sonst weiter belasten, obwohl die Grundlage entfallen ist.
     * 3. Die Gebuehr, falls der Grund eine vorsieht. Sie ist eine eigene
     *    Buchung vom Typ `fee` und keine Korrektur des offenen Betrags.
     * 4. Die Kaufsperre, und zwar erst jetzt: Sie haengt am Saldo *nach* der
     *    Gebuehr. Wer nichts schuldet, wird nicht gesperrt -- eine Sperre ohne
     *    Forderung waere eine Strafe ohne Zweck.
     *
     * Wiederholung ist folgenlos: Ein Wallet, das nicht (mehr) auf Postpaid
     * steht, wird unveraendert zurueckgegeben. Keine zweite Gebuehr, keine
     * zweite Mail.
     *
     * @param  string  $reason  PostpaidDowngradeRequested::REASON_*
     * @param  int|null  $feeCents  Gebuehr in Cent; null nimmt die Vorgabe des
     *                              Grundes (self::defaultFeeCentsFor()), 0 erhebt
     *                              ausdruecklich keine.
     */
    public function downgrade(Wallet $wallet, string $reason, ?int $feeCents = null): Wallet
    {
        $this->guardBuyerWallet($wallet);

        $fee = max(0, $feeCents ?? self::defaultFeeCentsFor($reason));

        $disabledAt = DB::transaction(function () use ($wallet, $reason): ?Carbon {
            /** @var Wallet|null $locked */
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Wallet || ! $locked->isPostpaid()) {
                return null;
            }

            $now = Carbon::now();

            $locked->forceFill([
                'payment_mode' => PaymentMode::PREPAID,
                'credit_limit_cents' => 0,
                'postpaid_disabled_at' => $now,
                'postpaid_disabled_reason' => $reason,
            ])->save();

            $this->failOpenSettlements($locked, $reason, $now);

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $now;
        });

        if (! $disabledAt instanceof Carbon) {
            // Nie freigeschaltet oder laengst zurueckgestuft. Beides ist der
            // Regelfall eines zweiten Ausloesers -- eine Ruecklastschrift
            // erreicht uns oft, nachdem der gescheiterte Einzug schon
            // zurueckgestuft hat.
            return $wallet;
        }

        if ($fee > 0) {
            $this->chargeFee($wallet, $reason, $fee, $disabledAt);
        }

        $blocked = $this->blockPurchasesIfOwing($wallet, $reason);

        Log::warning('Postpaid beendet.', [
            'wallet_id' => $wallet->getKey(),
            'reason' => $reason,
            'fee_cents' => $fee,
            'open_amount_cents' => $wallet->open_amount_cents,
            'purchase_blocked' => $blocked,
        ]);

        $this->audit->log(
            action: AuditAction::POSTPAID_DOWNGRADED,
            subject: $wallet,
            payload: [
                'wallet_id' => (int) $wallet->getKey(),
                'reason' => $reason,
                'fee_cents' => $fee,
                'open_amount_cents' => $wallet->open_amount_cents,
                'purchase_blocked' => $blocked,
            ],
            tenant: $wallet->owner,
        );

        $this->notifyDowngrade($wallet, $reason, $fee, $blocked);

        PostpaidDowngraded::dispatch(
            (int) $wallet->getKey(),
            $wallet->owner_id === null ? null : (int) $wallet->owner_id,
            $reason,
            $fee,
            $wallet->open_amount_cents,
            $blocked,
        );

        return $wallet;
    }

    /**
     * Die Gebuehr, die zu einem Rueckstufungsgrund gehoert.
     *
     * Der endgueltig gescheiterte Einzug kostet die Mahngebuehr, die
     * Ruecklastschrift und die angefochtene Kartenzahlung die
     * Ruecklastschriftgebuehr -- beide verursachen bei uns Kosten des
     * Zahlungsanbieters.
     *
     * Ohne Gebuehr bleiben die Faelle, in denen der Kaeufer nicht gezahlt hat,
     * weil er nicht zahlen konnte: das widerrufene Zahlungsmittel und das beim
     * Einzug fehlende. Da ist noch nichts schiefgegangen, was Geld gekostet
     * haette.
     */
    public static function defaultFeeCentsFor(string $reason): int
    {
        return match ($reason) {
            PostpaidDowngradeRequested::REASON_SETTLEMENT_FAILED => (int) config('wallet.postpaid.dunning_fee_cents'),
            PostpaidDowngradeRequested::REASON_SEPA_RETURN,
            PostpaidDowngradeRequested::REASON_CHARGEBACK => (int) config('wallet.postpaid.return_fee_cents'),
            default => 0,
        };
    }

    /**
     * Fuehrt dieser Grund zur Kaufsperre, sofern der Kaeufer etwas schuldet?
     *
     * Der Widerruf des Zahlungsmittels nicht: Er ist eine Entscheidung des
     * Kaeufers und keine Zahlungsstoerung. Er kauft danach wie jeder andere
     * mit Guthaben weiter und gleicht den offenen Betrag per Aufladung aus.
     */
    public static function blocksPurchases(string $reason): bool
    {
        return $reason !== PostpaidDowngradeRequested::REASON_PAYMENT_METHOD_REVOKED;
    }

    /**
     * Bucht die Gebuehr zulasten des Kaeufers.
     *
     * Mit `allowNegative`, weil der Saldo hier per Definition schon im Minus
     * steht: Eine Gebuehr, die an fehlender Deckung scheitert, waere genau
     * dort wirkungslos, wo sie gebraucht wird.
     *
     * Der Idempotenzschluessel traegt den Zeitpunkt der Rueckstufung. Damit ist
     * jede Rueckstufung genau eine Gebuehr -- eine zweite Stoerung Monate
     * spaeter bekommt ihre eigene, ein doppelt laufender Zuhoerer nicht.
     */
    private function chargeFee(Wallet $wallet, string $reason, int $feeCents, Carbon $disabledAt): void
    {
        try {
            $this->wallets->post(
                wallet: $wallet,
                type: WalletTransactionType::FEE,
                amountCents: -$feeCents,
                description: __('marketplace.wallet.descriptions.fee.'.$reason),
                reference: $wallet,
                idempotencyKey: WalletService::keyFor(
                    WalletTransactionType::FEE,
                    $wallet,
                    $reason.':'.$disabledAt->getTimestamp(),
                ),
                meta: ['reason' => $reason],
                allowNegative: true,
            );
        } catch (Throwable $exception) {
            // Die Rueckstufung steht bereits und ist der wichtigere Vorgang.
            // Eine nicht gebuchte Gebuehr ist nachholbar, eine halbe
            // Rueckstufung waere es nicht.
            Log::error('Postpaid: Gebuehr zur Rueckstufung konnte nicht gebucht werden.', [
                'wallet_id' => $wallet->getKey(),
                'reason' => $reason,
                'fee_cents' => $feeCents,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Setzt die Kaufsperre, wenn der Grund sie vorsieht und der Kaeufer
     * tatsaechlich etwas schuldet.
     *
     * Aufgehoben wird sie nicht hier, sondern im WalletService, sobald eine
     * Buchung den Saldo wieder auf null bringt (Ereignis WalletUnblocked).
     *
     * @return bool Ob die Sperre danach steht
     */
    private function blockPurchasesIfOwing(Wallet $wallet, string $reason): bool
    {
        if (! self::blocksPurchases($reason)) {
            return false;
        }

        $blocked = DB::transaction(function () use ($wallet): bool {
            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->balance_cents >= 0) {
                return false;
            }

            if (! $locked->purchase_blocked) {
                $locked->forceFill(['purchase_blocked' => true])->save();
            }

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        return $blocked;
    }

    /**
     * Beendet die noch nicht angestossenen oder auf den zweiten Versuch
     * wartenden Einzuege dieses Wallets.
     *
     * Unterwegs befindliche Einzuege (`processing`) bleiben unberuehrt: Bei
     * ihnen laeuft die Zahlung beim Anbieter, ihr Ergebnis meldet der Webhook.
     * Sie hier zu beenden hiesse, einen moeglicherweise eingehenden Betrag
     * nicht mehr gutzuschreiben.
     */
    private function failOpenSettlements(Wallet $wallet, string $reason, Carbon $now): void
    {
        Settlement::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereIn('status', [SettlementStatus::PENDING, SettlementStatus::RETRY_PENDING])
            ->get()
            ->each(function (Settlement $settlement) use ($reason, $now): void {
                $settlement->forceFill([
                    'status' => SettlementStatus::FAILED,
                    'failed_at' => $settlement->failed_at ?? $now,
                    'failure_reason' => 'downgraded:'.$reason,
                    'next_attempt_at' => null,
                ])->save();
            });
    }

    /**
     * Meldet die Rueckstufung dem Kaeufer und dem Betreiber.
     *
     * Derselbe Text an beide: Der Betreiber soll lesen, was der Kaeufer liest
     * -- eine Rueckfrage am Telefon beantwortet sich sonst aus zwei
     * verschiedenen Staenden.
     */
    private function notifyDowngrade(Wallet $wallet, string $reason, int $feeCents, bool $blocked): void
    {
        $buyer = $wallet->owner;

        if (! $buyer instanceof Tenant) {
            return;
        }

        $mail = fn (): PostpaidDowngradedMail => new PostpaidDowngradedMail(
            $buyer,
            $reason,
            $feeCents,
            $wallet->open_amount_cents,
            $blocked,
        );

        $email = (string) ($buyer->users()->value('users.email') ?? '');

        if ($email !== '') {
            $this->sendSafely(
                fn () => Mail::to($email)->send($mail()),
                ['wallet_id' => $wallet->getKey(), 'recipient' => 'buyer'],
            );
        } else {
            Log::warning('Postpaid: Rueckstufung ohne Empfaenger beim Kaeufer.', [
                'wallet_id' => $wallet->getKey(),
                'tenant_id' => $buyer->getKey(),
            ]);
        }

        $operator = $this->support->addressOrLog(
            'Rueckstufung ohne Meldung an den Betreiber: app.support_email ist nicht brauchbar gesetzt.',
            ['wallet_id' => $wallet->getKey()],
        );

        if ($operator !== null) {
            $this->sendSafely(
                fn () => Mail::to($operator)->send($mail()->forOperator()),
                ['wallet_id' => $wallet->getKey(), 'recipient' => 'operator'],
            );
        }
    }

    /**
     * Versand, der die Rueckstufung nicht mitreisst.
     *
     * @param  Closure(): mixed  $send
     * @param  array<string, mixed>  $context
     */
    private function sendSafely(Closure $send, array $context): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::error('Postpaid-Mail konnte nicht versendet werden.', $context + [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Laeuft das Verfahren ueberhaupt? Der Hauptschalter des Rollouts.
     */
    public function isEnabled(): bool
    {
        return (bool) config('wallet.postpaid.enabled');
    }

    /**
     * Der offene Antrag eines Wallets, falls es einen gibt.
     */
    public function pendingApplication(Wallet $wallet): ?PostpaidApplication
    {
        return $wallet->postpaidApplications()->pending()->first();
    }

    /**
     * Verbleibende Sperrtage nach einer Ablehnung; 0, wenn wieder beantragt
     * werden darf.
     */
    public function reapplyBlockedDays(Wallet $wallet): int
    {
        $waitDays = (int) config('wallet.postpaid.reapply_after_days');

        if ($waitDays <= 0) {
            return 0;
        }

        /** @var PostpaidApplication|null $rejected */
        $rejected = $wallet->postpaidApplications()
            ->where('status', PostpaidApplication::STATUS_REJECTED)
            ->orderByDesc('decided_at')
            ->first();

        $decidedAt = $rejected?->decided_at;

        if ($decidedAt === null) {
            return 0;
        }

        $allowedFrom = $decidedAt->copy()->addDays($waitDays);

        return $allowedFrom->isFuture() ? (int) ceil(now()->diffInDays($allowedFrom, true)) : 0;
    }

    /**
     * Entscheidung ueber einen offenen Antrag, samt der Aenderungen am Wallet.
     *
     * Der Antrag wird gesperrt neu geladen, weil zwischen Aufruf und
     * Entscheidung ein anderer Vorgang dieselbe Zeile entschieden haben kann.
     * Steht sie dann schon im Zielzustand, ist der Aufruf eine Wiederholung
     * und bleibt folgenlos -- Mail und Audit-Eintrag entstehen nur einmal.
     *
     * @param  Closure(PostpaidApplication): void|null  $apply
     * @return bool Ob diese Entscheidung neu war
     *
     * @throws PostpaidNotAllowedException
     */
    private function decide(
        PostpaidApplication $application,
        string $target,
        User $admin,
        ?string $note,
        ?Closure $apply,
    ): bool {
        if ($application->status === $target) {
            return false;
        }

        return DB::transaction(function () use ($application, $target, $admin, $note, $apply): bool {
            /** @var PostpaidApplication $locked */
            $locked = PostpaidApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === $target) {
                $application->setRawAttributes($locked->getAttributes(), true);

                return false;
            }

            if (! $locked->isPending()) {
                throw PostpaidNotAllowedException::alreadyDecided((int) $locked->getKey(), $locked->status);
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->forceFill([
                'status' => $target,
                'decided_at' => now(),
                'decided_by' => (int) $admin->getKey(),
                'note' => $note === null || $note === '' ? $locked->note : $note,
            ])->save();

            $application->setRawAttributes($locked->getAttributes(), true);
            $application->setRelations($locked->getRelations());

            return true;
        });
    }

    /**
     * Abgerechnete Leadkaeufe des Kaeufers. Gezaehlt wird `captured` und nicht
     * `reserved`: Erst der abgerechnete Kauf belegt, dass der Kaeufer
     * tatsaechlich gezahlt hat.
     */
    private function capturedPurchases(Tenant $buyer): int
    {
        return LeadPurchase::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->where('status', PurchaseStatus::CAPTURED)
            ->count();
    }

    /**
     * Alter des Kontos in ganzen Tagen.
     */
    private function accountAgeDays(Tenant $buyer): int
    {
        $createdAt = $buyer->created_at;

        return $createdAt === null ? 0 : (int) floor($createdAt->diffInDays(now(), true));
    }

    /**
     * Gescheiterte und zurueckgegebene Einzuege im Beobachtungszeitraum.
     */
    private function failedSettlements(Wallet $wallet, Carbon $since): int
    {
        return Settlement::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereIn('status', [SettlementStatus::FAILED, SettlementStatus::RETURNED])
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Rueckbelastungen im Beobachtungszeitraum. Sie stehen als Korrektur-
     * buchung mit `meta.reason = chargeback` im Journal
     * (App\Listeners\Order\CreditWalletAfterPayment).
     */
    private function chargebacks(Wallet $wallet, Carbon $since): int
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('type', WalletTransactionType::ADJUSTMENT)
            ->where('meta->reason', 'chargeback')
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Der Grund einer frueheren Rueckstufung, sofern er eine Zahlungsstoerung
     * belegt und im Beobachtungszeitraum liegt.
     */
    private function disqualifyingDisableReason(Wallet $wallet, Carbon $since): ?string
    {
        $reason = $wallet->postpaid_disabled_reason;

        if ($reason === null || ! in_array($reason, self::DISQUALIFYING_DISABLE_REASONS, true)) {
            return null;
        }

        $disabledAt = $wallet->postpaid_disabled_at;

        // Ohne Zeitstempel zaehlt die Stoerung: Ein Beleg ohne Datum laesst
        // sich nicht als "lange her" abtun.
        return $disabledAt === null || $disabledAt->greaterThanOrEqualTo($since) ? $reason : null;
    }

    /**
     * Meldet den neuen Antrag an die Betreiberadresse. Freigegeben wird von
     * Hand -- ohne diese Meldung bliebe der Antrag liegen, bis jemand von sich
     * aus in die Liste schaut.
     */
    private function notifyOperator(PostpaidApplication $application, Wallet $wallet, ?User $user): void
    {
        $recipient = $this->support->addressOrLog(
            'Postpaid-Antrag ohne Meldung an den Betreiber: app.support_email ist nicht brauchbar gesetzt.',
            ['postpaid_application_id' => $application->getKey()],
        );

        if ($recipient === null) {
            return;
        }

        $buyer = $wallet->owner;

        if (! $buyer instanceof Tenant) {
            return;
        }

        $this->send($application, fn () => Mail::to($recipient)
            ->send(new PostpaidApplicationReceivedMail($application, $buyer, $user)));
    }

    /**
     * Meldet die Entscheidung dem Kaeufer. Empfaenger ist ein Benutzer des
     * Mandanten -- die Freischaltung gilt dem Mandanten, nicht einer Person.
     *
     * @param  Closure(Tenant, string): mixed  $send
     */
    private function notifyBuyer(PostpaidApplication $application, Closure $send): void
    {
        $buyer = $application->wallet?->owner;

        if (! $buyer instanceof Tenant) {
            return;
        }

        $recipient = $buyer->users()->first();

        if (! $recipient instanceof User || ! is_string($recipient->email) || $recipient->email === '') {
            return;
        }

        $this->send($application, fn () => $send($buyer, $recipient->email));
    }

    /**
     * Versand, der den Vorgang nicht mitreisst. Mit QUEUE_CONNECTION=sync
     * laeuft der Mailversand im Request mit; Antrag und Freischaltung stehen
     * zu diesem Zeitpunkt fest und duerfen an einem Mailserver nicht
     * scheitern.
     *
     * @param  Closure(): mixed  $send
     */
    private function send(PostpaidApplication $application, Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::error('Postpaid-Mail konnte nicht versendet werden.', [
                'postpaid_application_id' => $application->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pay as you go gibt es nur am Kauf-Wallet.
     */
    private function guardBuyerWallet(Wallet $wallet): void
    {
        if ($wallet->owner_type !== WalletOwnerType::BUYER) {
            throw PostpaidNotAllowedException::notABuyerWallet();
        }
    }
}
