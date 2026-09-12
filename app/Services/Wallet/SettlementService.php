<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\PaymentMethodStatus;
use App\Constants\SettlementStatus;
use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Jobs\ChargeSettlementJob;
use App\Mail\Wallet\SettlementChargeErrorMail;
use App\Mail\Wallet\SettlementFailedMail;
use App\Mail\Wallet\SettlementPaidMail;
use App\Models\PaymentMethod;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SupportMailbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Throwable;

/**
 * Der Einzug des offenen Betrags eines Postpaid-Kaeufers (LP-POSTPAID-008).
 *
 * Diese Klasse ist die einzige Stelle, die `settlements` schreibt. Sie ist das
 * Gegenstueck zum PayoutService auf der Verkaeuferseite und arbeitet nach
 * demselben Grundsatz: Jede Geldbewegung geht durch den WalletService, jede
 * Zustandsaenderung passiert unter Sperre.
 *
 * Der Ablauf ist dreistufig und bewusst auseinandergezogen:
 *
 * 1. `create()` friert den offenen Betrag als Forderung ein. Der Betrag ist
 *    ein Snapshot: Kaeufe zwischen Erstellung und Zahlungseingang wachsen dem
 *    naechsten Settlement zu, nicht diesem. Sonst waere der Betrag, der dem
 *    Kaeufer per SEPA angekuendigt wurde, beim Einzug ein anderer.
 * 2. `charge()` belastet das hinterlegte Zahlungsmittel ueber Stripe. Ergebnis
 *    offen -- der Zustand ist `processing`.
 * 3. Der Webhook meldet Erfolg oder Fehlschlag; erst dort wird gebucht
 *    (`markPaid()`) oder der zweite Versuch angesetzt (`markFailed()`).
 *
 * Gebucht wird ausschliesslich beim Erfolg, nicht beim Anstossen: Eine
 * Lastschrift kann bis zu 14 Tage unterwegs sein und danach immer noch
 * scheitern. Waehrend dieser Zeit bleibt das Settlement `processing`, es wird
 * kein zweiter Einzug angestossen, und der Kaeufer darf innerhalb seines
 * Rahmens weiterkaufen -- sein Kredit ist gewaehrt, solange nichts schiefging.
 */
class SettlementService
{
    /** Zahl der Einzugsversuche, nach denen endgueltig gescheitert wird. */
    public const MAX_ATTEMPTS = 2;

    /** Kein einsatzbereites Zahlungsmittel hinterlegt. */
    public const REASON_NO_PAYMENT_METHOD = 'no_payment_method';

    /** Stoerung auf unserer Seite, kein Zahlungsverzug des Kunden. */
    public const REASON_TECHNICAL = 'technical_error';

    public function __construct(
        private WalletService $wallets,
        private SettlementPrenotifier $prenotifier,
        private SettlementInvoiceIssuer $invoices,
        private SupportMailbox $support,
    ) {}

    /**
     * Friert den offenen Betrag eines Wallets als Forderung ein.
     *
     * Gibt null zurueck, wenn es nichts einzuziehen gibt oder bereits ein
     * Einzug laeuft. Beides ist der Regelfall eines Scheduler-Laufs und kein
     * Fehler.
     *
     * Ohne aktives Zahlungsmittel entsteht trotzdem ein Settlement, und zwar
     * sofort als `failed`: Die Forderung ist entstanden und soll sichtbar
     * sein. Ein stilles Ueberspringen wuerde einen Kaeufer ohne Zahlungsmittel
     * unbegrenzt weiterlaufen lassen.
     *
     * @param  string  $trigger  Settlement::TRIGGER_*
     */
    public function create(Wallet $wallet, string $trigger = Settlement::TRIGGER_SCHEDULED): ?Settlement
    {
        $settlement = DB::transaction(function () use ($wallet, $trigger): ?Settlement {
            $locked = Wallet::query()->lockForUpdate()->find($wallet->getKey());

            if (! $locked instanceof Wallet) {
                return null;
            }

            if ($locked->owner_type !== WalletOwnerType::BUYER) {
                return null;
            }

            if ($locked->balance_cents >= 0) {
                return null;
            }

            // Hoechstens ein unentschiedener Einzug je Wallet. Ein zweiter
            // wuerde denselben offenen Betrag ein zweites Mal einziehen -- die
            // Pruefung steht unter der Wallet-Sperre, damit zwei gleichzeitige
            // Laeufe (Termin und Schwelle) sich nicht gegenseitig ueberholen.
            if (Settlement::query()->where('wallet_id', $locked->getKey())->unresolved()->exists()) {
                return null;
            }

            $method = $this->usableDefaultMethod($locked);

            return Settlement::query()->create([
                'wallet_id' => $locked->getKey(),
                'amount_cents' => -$locked->balance_cents,
                'status' => $method instanceof PaymentMethod
                    ? SettlementStatus::PENDING
                    : SettlementStatus::FAILED,
                'trigger' => $trigger,
                'payment_method_id' => $method?->getKey(),
                'attempts' => 0,
                'failed_at' => $method instanceof PaymentMethod ? null : Carbon::now(),
                'failure_reason' => $method instanceof PaymentMethod ? null : self::REASON_NO_PAYMENT_METHOD,
            ]);
        });

        if (! $settlement instanceof Settlement) {
            return null;
        }

        if ($settlement->status === SettlementStatus::FAILED) {
            Log::warning('Postpaid: Einzug ohne Zahlungsmittel, Rueckstufung angestossen.', [
                'settlement_id' => $settlement->getKey(),
                'wallet_id' => $settlement->wallet_id,
                'amount_cents' => $settlement->amount_cents,
            ]);

            PostpaidDowngradeRequested::dispatch(
                (int) $settlement->wallet_id,
                PostpaidDowngradeRequested::REASON_NO_PAYMENT_METHOD,
            );
        }

        return $settlement;
    }

    /**
     * Einzug durch den Admin (LP-POSTPAID-012). Derselbe Weg, nur ein anderer
     * Ausloeser -- der Admin darf nicht mehr duerfen als der Scheduler, sonst
     * gaebe es zwei Wahrheiten ueber den offenen Betrag.
     */
    public function createManual(Wallet $wallet, User $admin): ?Settlement
    {
        $settlement = $this->create($wallet, Settlement::TRIGGER_MANUAL);

        if ($settlement instanceof Settlement) {
            Log::info('Postpaid: Einzug von Hand angestossen.', [
                'settlement_id' => $settlement->getKey(),
                'wallet_id' => $settlement->wallet_id,
                'admin_id' => $admin->getKey(),
            ]);

            if ($settlement->status === SettlementStatus::PENDING) {
                // Wer von Hand einzieht, will nicht bis zum naechsten
                // stuendlichen Lauf warten. Belastet wird trotzdem asynchron
                // und erst nach der SEPA-Ankuendigung -- darum kuemmert sich
                // charge() selbst.
                $this->dispatchCharge($settlement);
            }
        }

        return $settlement;
    }

    /**
     * Erneuter Einzugsversuch zu einer gescheiterten Forderung
     * (LP-POSTPAID-012).
     *
     * Gedacht fuer den Fall, dass die Ursache des Fehlschlags behoben ist --
     * neues Zahlungsmittel, gedecktes Konto. Gibt null zurueck, wenn es nichts
     * zu wiederholen gibt: Der Einzug ist nicht gescheitert, der Kaeufer
     * schuldet nichts mehr, ein anderer Einzug laeuft bereits oder es fehlt
     * ein einsatzbereites Zahlungsmittel.
     *
     * Der Betrag wird dabei auf den heute offenen Betrag gesetzt und nicht aus
     * dem alten Versuch uebernommen: Zwischen Fehlschlag und Wiederholung kann
     * der Kaeufer aufgeladen oder weiter gekauft haben, und eingezogen werden
     * darf nur, was er wirklich schuldet.
     *
     * Die Zahl der Versuche laeuft weiter und wird nicht zurueckgesetzt. Sie
     * steckt im Idempotenzschluessel des Stripe-Aufrufs -- mit dem Schluessel
     * eines frueheren Versuchs bekaeme man dessen gescheiterten PaymentIntent
     * zurueck statt einer neuen Belastung.
     */
    public function retry(Settlement $settlement, User $admin): ?Settlement
    {
        $reopened = DB::transaction(function () use ($settlement): ?Settlement {
            /** @var Settlement|null $locked */
            $locked = Settlement::query()->lockForUpdate()->find($settlement->getKey());

            if (! $locked instanceof Settlement || $locked->status !== SettlementStatus::FAILED) {
                return null;
            }

            /** @var Wallet|null $wallet */
            $wallet = Wallet::query()->lockForUpdate()->find($locked->wallet_id);

            if (! $wallet instanceof Wallet || $wallet->balance_cents >= 0) {
                return null;
            }

            if (Settlement::query()->where('wallet_id', $wallet->getKey())->unresolved()->exists()) {
                return null;
            }

            $method = $this->usableDefaultMethod($wallet);

            if (! $method instanceof PaymentMethod) {
                return null;
            }

            $locked->forceFill([
                'status' => SettlementStatus::PENDING,
                'amount_cents' => -$wallet->balance_cents,
                'payment_method_id' => $method->getKey(),
                'failed_at' => null,
                'failure_reason' => null,
                'next_attempt_at' => null,
                // Der wiederholte Einzug hat ein anderes Belastungsdatum und
                // muss deshalb erneut angekuendigt werden.
                'prenotified_at' => null,
                'charge_due_at' => null,
            ])->save();

            $settlement->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });

        if (! $reopened instanceof Settlement) {
            return null;
        }

        Log::info('Postpaid: Einzug von Hand wiederholt.', [
            'settlement_id' => $reopened->getKey(),
            'wallet_id' => $reopened->wallet_id,
            'amount_cents' => $reopened->amount_cents,
            'attempts' => $reopened->attempts,
            'admin_id' => $admin->getKey(),
        ]);

        $this->dispatchCharge($reopened);

        return $reopened;
    }

    /**
     * Zieht sofort ein, wenn der offene Betrag die Schwelle erreicht hat
     * (config('wallet.postpaid.settlement_threshold_cents')).
     *
     * Gerufen nach jeder Abbuchung eines Postpaid-Kaufs. Ohne diese Pruefung
     * koennte ein Kaeufer binnen Tagen seinen ganzen Rahmen ausschoepfen und
     * der Ausfall fiele erst am Wochentermin auf.
     */
    public function checkThreshold(Wallet $wallet): ?Settlement
    {
        $threshold = (int) config('wallet.postpaid.settlement_threshold_cents');

        if ($threshold <= 0 || $wallet->open_amount_cents < $threshold) {
            return null;
        }

        $settlement = $this->create($wallet, Settlement::TRIGGER_THRESHOLD);

        if ($settlement instanceof Settlement && $settlement->status === SettlementStatus::PENDING) {
            // Nicht hier belasten: Der Aufrufer sitzt in der Abrechnung eines
            // Leads, und ein Stripe-Aufruf an dieser Stelle wuerde die
            // Abrechnung von der Erreichbarkeit des Zahlungsanbieters abhaengig
            // machen. Der Einzug laeuft asynchron.
            $this->dispatchCharge($settlement);
        }

        return $settlement;
    }

    /**
     * Belastet das hinterlegte Zahlungsmittel ueber Stripe.
     *
     * Gibt das Settlement unveraendert zurueck, wenn gerade nicht belastet
     * werden darf -- entschieden, unterwegs, Wiederholung noch nicht faellig
     * oder SEPA-Ankuendigung noch nicht draussen (LP-POSTPAID-014).
     *
     * Der Zustand danach ist `processing`: Ob die Zahlung durchgeht,
     * entscheidet der Webhook. Auch eine Karte, die Stripe sofort bestaetigt,
     * laeuft ueber diesen Weg -- ein zweiter Buchungspfad waere eine zweite
     * Gelegenheit, dieselbe Zahlung doppelt gutzuschreiben.
     *
     * @throws ApiErrorException bei technischen Stoerungen; der Job wiederholt.
     */
    public function charge(Settlement $settlement): Settlement
    {
        if (! $settlement->status->isOpen()) {
            return $settlement;
        }

        if ($settlement->status === SettlementStatus::RETRY_PENDING
            && $settlement->next_attempt_at instanceof Carbon
            && $settlement->next_attempt_at->isFuture()) {
            return $settlement;
        }

        $method = $settlement->paymentMethod;

        if (! $method instanceof PaymentMethod || $method->status !== PaymentMethodStatus::ACTIVE) {
            return $this->finalize(
                $settlement,
                self::REASON_NO_PAYMENT_METHOD,
                PostpaidDowngradeRequested::REASON_NO_PAYMENT_METHOD,
            );
        }

        if (! $settlement->mayBeCharged()) {
            // Ankuendigung nachholen und den naechsten Lauf abwarten. Frueher
            // zu belasten als angekuendigt waere eine andere Buchung als die
            // angekuendigte und damit angreifbar.
            $this->prenotifier->announce($settlement);

            return $settlement;
        }

        $settlement->attempts = (int) $settlement->attempts + 1;
        $settlement->save();

        try {
            $intent = $this->client()->paymentIntents->create(
                $this->intentPayload($settlement, $method),
                ['idempotency_key' => $this->stripeIdempotencyKey($settlement)],
            );
        } catch (CardException $exception) {
            // Ablehnung des Zahlungsmittels: Das ist ein Zahlungsfehler und
            // keine Stoerung. Stripe schickt dazu zusaetzlich
            // `payment_intent.payment_failed`; die Behandlung ist ueber den
            // Zustand idempotent, es zaehlt, wer zuerst kommt.
            return $this->markFailed(
                $settlement,
                (string) ($exception->getError()->code ?? $exception->getMessage()),
            );
        }

        $settlement->status = SettlementStatus::PROCESSING;
        $settlement->provider_payment_intent_id = (string) $intent->id;
        $settlement->save();

        Log::info('Postpaid: Einzug angestossen.', [
            'settlement_id' => $settlement->getKey(),
            'wallet_id' => $settlement->wallet_id,
            'amount_cents' => $settlement->amount_cents,
            'attempts' => $settlement->attempts,
            'payment_intent' => $settlement->provider_payment_intent_id,
        ]);

        return $settlement;
    }

    /**
     * Der Einzug ist eingegangen: buchen, abschliessen, Beleg und Mail.
     *
     * Die Gutschrift laeuft ueber den WalletService und traegt einen festen
     * Idempotenzschluessel -- eine zweite Zustellung desselben Webhooks bucht
     * deshalb nicht ein zweites Mal, selbst wenn die Ereignissperre
     * (StripeWebhookEvent) einmal nicht greifen sollte.
     */
    public function markPaid(Settlement $settlement, ?string $paymentIntentId = null): Settlement
    {
        $settlement = DB::transaction(function () use ($settlement, $paymentIntentId): Settlement {
            $locked = Settlement::query()->lockForUpdate()->find($settlement->getKey());

            if (! $locked instanceof Settlement || $locked->status === SettlementStatus::PAID) {
                return $locked ?? $settlement;
            }

            $wallet = $locked->wallet;

            if (! $wallet instanceof Wallet) {
                return $locked;
            }

            $this->wallets->post(
                wallet: $wallet,
                type: WalletTransactionType::SETTLEMENT,
                amountCents: (int) $locked->amount_cents,
                description: __('marketplace.wallet.descriptions.settlement', ['settlement' => $locked->getKey()]),
                reference: $locked,
                idempotencyKey: WalletService::keyFor(WalletTransactionType::SETTLEMENT, $locked),
                meta: [
                    'trigger' => $locked->trigger,
                    'payment_method_id' => $locked->payment_method_id,
                    'payment_intent' => $paymentIntentId ?? $locked->provider_payment_intent_id,
                ],
            );

            $locked->status = SettlementStatus::PAID;
            $locked->paid_at = Carbon::now();
            $locked->failure_reason = null;

            if ($paymentIntentId !== null && $paymentIntentId !== '') {
                $locked->provider_payment_intent_id = $paymentIntentId;
            }

            $locked->save();

            return $locked;
        });

        if ($settlement->status !== SettlementStatus::PAID) {
            return $settlement;
        }

        $this->issueInvoice($settlement);
        $this->notifyPaid($settlement);

        return $settlement;
    }

    /**
     * Der Einzug ist gescheitert.
     *
     * Erster Versuch: zweiter Versuch nach `retry_after_days`. Die
     * SEPA-Ankuendigung wird dabei zurueckgesetzt -- der zweite Einzug hat ein
     * anderes Belastungsdatum und muss deshalb erneut angekuendigt werden.
     *
     * Zweiter Versuch: endgueltig gescheitert, es folgt die Rueckstufung
     * (LP-POSTPAID-009).
     */
    public function markFailed(Settlement $settlement, string $reason): Settlement
    {
        if ($settlement->status->isResolved()) {
            return $settlement;
        }

        if ((int) $settlement->attempts >= self::MAX_ATTEMPTS) {
            return $this->finalize($settlement, $reason);
        }

        $settlement->status = SettlementStatus::RETRY_PENDING;
        $settlement->failure_reason = $reason;
        $settlement->next_attempt_at = Carbon::now()->addDays(
            max(1, (int) config('wallet.postpaid.retry_after_days', 3)),
        );
        $settlement->prenotified_at = null;
        $settlement->charge_due_at = null;
        $settlement->save();

        Log::warning('Postpaid: Einzug gescheitert, zweiter Versuch angesetzt.', [
            'settlement_id' => $settlement->getKey(),
            'wallet_id' => $settlement->wallet_id,
            'reason' => $reason,
            'next_attempt_at' => $settlement->next_attempt_at->toDateTimeString(),
        ]);

        $this->notifyFailed($settlement, final: false);

        return $settlement;
    }

    /**
     * Eine bereits gutgeschriebene Zahlung wurde zurueckgegeben
     * (LP-POSTPAID-009).
     *
     * Zwei Wege fuehren hierher, beide vom Postpaid-Webhook: die
     * Ruecklastschrift (SEPA, `payment_intent.payment_failed` nach der
     * Gutschrift) und die angefochtene Kartenzahlung
     * (`charge.dispute.created`). Fachlich ist es derselbe Vorgang -- das Geld
     * war da und ist wieder weg.
     *
     * Die Rueckbuchung ist eine Korrektur (`adjustment`) und kein negativer
     * `settlement`: Die urspruengliche Gutschrift bleibt im Journal stehen und
     * wird durch eine eigene Zeile aufgehoben, statt nachtraeglich zu
     * verschwinden. Ihr Schluessel ist `return:{payment_intent_id}` -- an der
     * Zahlung, nicht am Settlement, weil Stripe dasselbe Ereignis mehrfach
     * zustellen darf.
     *
     * @param  string  $reason  `sepa_return` oder `chargeback`
     */
    public function markReturned(Settlement $settlement, string $reason, ?string $paymentIntentId = null): Settlement
    {
        $settlement = DB::transaction(function () use ($settlement, $reason, $paymentIntentId): Settlement {
            /** @var Settlement|null $locked */
            $locked = Settlement::query()->lockForUpdate()->find($settlement->getKey());

            if (! $locked instanceof Settlement || $locked->status === SettlementStatus::RETURNED) {
                return $locked ?? $settlement;
            }

            if ($locked->status !== SettlementStatus::PAID) {
                // Nichts gutgeschrieben, also nichts zurueckzubuchen. Der
                // gescheiterte Einzug hat seinen eigenen Weg (markFailed).
                Log::warning('Postpaid: Rueckgabe zu einem nicht gutgeschriebenen Einzug gemeldet.', [
                    'settlement_id' => $locked->getKey(),
                    'status' => $locked->status->value,
                    'reason' => $reason,
                ]);

                return $locked;
            }

            $wallet = $locked->wallet;

            if (! $wallet instanceof Wallet) {
                return $locked;
            }

            $intentId = $paymentIntentId !== null && $paymentIntentId !== ''
                ? $paymentIntentId
                : (string) $locked->provider_payment_intent_id;

            $this->wallets->post(
                wallet: $wallet,
                type: WalletTransactionType::ADJUSTMENT,
                amountCents: -(int) $locked->amount_cents,
                description: __('marketplace.wallet.descriptions.settlement_return', ['settlement' => $locked->getKey()]),
                reference: $locked,
                idempotencyKey: 'return:'.($intentId !== '' ? $intentId : 'settlement:'.$locked->getKey()),
                meta: ['reason' => $reason, 'payment_intent' => $intentId],
                allowNegative: true,
            );

            $locked->status = SettlementStatus::RETURNED;
            $locked->failed_at = Carbon::now();
            $locked->failure_reason = $reason;
            $locked->next_attempt_at = null;
            $locked->save();

            return $locked;
        });

        if ($settlement->status !== SettlementStatus::RETURNED) {
            return $settlement;
        }

        Log::warning('Postpaid: Zahlung zurueckgegeben, Rueckstufung angestossen.', [
            'settlement_id' => $settlement->getKey(),
            'wallet_id' => $settlement->wallet_id,
            'amount_cents' => $settlement->amount_cents,
            'reason' => $reason,
        ]);

        PostpaidDowngradeRequested::dispatch(
            (int) $settlement->wallet_id,
            $reason,
            $settlement->payment_method_id === null ? null : (int) $settlement->payment_method_id,
        );

        return $settlement;
    }

    /**
     * Technische Stoerung beim Anstossen des Einzugs (Netz, Stripe-API).
     *
     * Das ist kein Zahlungsverzug des Kunden, deshalb keine Rueckstufung und
     * keine Mahnung: Das Settlement wird stillgelegt und ein Mensch bekommt
     * Bescheid. Gerufen vom ChargeSettlementJob nach dem letzten Versuch.
     */
    public function markTechnicallyFailed(Settlement $settlement, string $reason): Settlement
    {
        if ($settlement->status->isResolved()) {
            return $settlement;
        }

        $settlement->status = SettlementStatus::FAILED;
        $settlement->failed_at = Carbon::now();
        $settlement->failure_reason = self::REASON_TECHNICAL.': '.$reason;
        $settlement->save();

        Log::error('Postpaid: Einzug wegen technischer Stoerung liegengeblieben.', [
            'settlement_id' => $settlement->getKey(),
            'wallet_id' => $settlement->wallet_id,
            'reason' => $reason,
        ]);

        $recipient = $this->support->addressOrLog(
            'Technisch gescheiterter Einzug ohne Support-Meldung: app.support_email ist nicht brauchbar gesetzt.',
            ['settlement_id' => $settlement->getKey()],
        );

        if ($recipient !== null) {
            Mail::to($recipient)->send(new SettlementChargeErrorMail($settlement, $reason));
        }

        return $settlement;
    }

    /**
     * Stellt den Einzug in die Queue. Eigener Weg, damit weder eine
     * Lead-Abrechnung noch ein Webhook auf Stripe warten muss.
     */
    public function dispatchCharge(Settlement $settlement): void
    {
        ChargeSettlementJob::dispatch((int) $settlement->getKey());
    }

    /**
     * Endgueltiger Fehlschlag: Zustand, Grund und die Rueckstufung
     * (LP-POSTPAID-009). Die Forderung bleibt bestehen -- der Saldo des
     * Kaeufers ist weiterhin negativ.
     */
    private function finalize(
        Settlement $settlement,
        string $reason,
        string $downgradeReason = PostpaidDowngradeRequested::REASON_SETTLEMENT_FAILED,
    ): Settlement {
        $settlement->status = SettlementStatus::FAILED;
        $settlement->failed_at = Carbon::now();
        $settlement->failure_reason = $reason;
        $settlement->next_attempt_at = null;
        $settlement->save();

        Log::warning('Postpaid: Einzug endgueltig gescheitert, Rueckstufung angestossen.', [
            'settlement_id' => $settlement->getKey(),
            'wallet_id' => $settlement->wallet_id,
            'reason' => $reason,
            'attempts' => $settlement->attempts,
        ]);

        PostpaidDowngradeRequested::dispatch(
            (int) $settlement->wallet_id,
            $downgradeReason,
            $settlement->payment_method_id === null ? null : (int) $settlement->payment_method_id,
        );

        $this->notifyFailed($settlement, final: true);

        return $settlement;
    }

    /**
     * Das Standardmittel, mit dem eingezogen wird -- nur wenn es einsatzbereit
     * ist und bei SEPA ein belegtes Mandat traegt. Eine Lastschrift ohne
     * Mandatsnachweis waere angreifbar (LP-POSTPAID-005).
     */
    private function usableDefaultMethod(Wallet $wallet): ?PaymentMethod
    {
        $method = $wallet->defaultPaymentMethod()->first();

        if (! $method instanceof PaymentMethod || ! $method->hasValidMandate()) {
            return null;
        }

        return $method;
    }

    /**
     * Der Rumpf des PaymentIntent.
     *
     * `off_session` und `confirm` gehoeren zusammen: Es sitzt niemand am
     * Bildschirm, der eine 3DS-Abfrage bestaetigen koennte. Deshalb wurde das
     * Zahlungsmittel schon beim Hinterlegen mit `usage => off_session`
     * eingerichtet (LP-POSTPAID-005).
     *
     * @return array<string, mixed>
     */
    private function intentPayload(Settlement $settlement, PaymentMethod $method): array
    {
        $payload = [
            'amount' => (int) $settlement->amount_cents,
            'currency' => strtolower((string) $settlement->wallet->currency),
            'customer' => $method->provider_customer_id,
            'payment_method' => $method->provider_payment_method_id,
            'payment_method_types' => [$method->type->value],
            'off_session' => true,
            'confirm' => true,
            'description' => __('marketplace.wallet.descriptions.settlement', ['settlement' => $settlement->getKey()]),
            'metadata' => [
                'settlement_id' => (string) $settlement->getKey(),
                'wallet_id' => (string) $settlement->wallet_id,
                'purpose' => 'postpaid_settlement',
            ],
        ];

        // Bei SEPA verlangt Stripe beim Einzug das Mandat, unter dem belastet
        // wird. Ohne diese Angabe lehnt es die Belastung off-session ab.
        if ($method->provider_mandate_id !== null && $method->provider_mandate_id !== '') {
            $payload['mandate'] = $method->provider_mandate_id;
        }

        return $payload;
    }

    /**
     * Der Idempotenzschluessel des Stripe-Aufrufs. Er traegt die Zahl der
     * Versuche, weil der zweite Einzugsversuch derselben Forderung ein neuer
     * PaymentIntent sein muss -- mit dem Schluessel des ersten wuerde Stripe
     * den alten, gescheiterten Intent zurueckgeben.
     */
    private function stripeIdempotencyKey(Settlement $settlement): string
    {
        $attempt = max(1, (int) $settlement->attempts);

        return 'settlement:'.$settlement->getKey().($attempt > 1 ? ':'.$attempt : '');
    }

    /**
     * Beleg erzeugen und seine Nummer festhalten (LP-POSTPAID-015).
     *
     * Scheitert der Beleg, bleibt der Einzug gueltig: Das Geld ist eingegangen
     * und gebucht. Ein fehlender Beleg ist nachholbar, eine zurueckgedrehte
     * Buchung waere es nicht.
     */
    private function issueInvoice(Settlement $settlement): void
    {
        if ($settlement->invoice_reference !== null) {
            return;
        }

        try {
            $reference = $this->invoices->issue($settlement);
        } catch (Throwable $exception) {
            Log::warning('Postpaid: Beleg zum Einzug konnte nicht erzeugt werden.', [
                'settlement_id' => $settlement->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if ($reference === null || $reference === '') {
            return;
        }

        $settlement->invoice_reference = $reference;
        $settlement->save();
    }

    private function notifyPaid(Settlement $settlement): void
    {
        $this->mailBuyer($settlement, fn (Tenant $buyer, string $to) => Mail::to($to)->send(
            new SettlementPaidMail($settlement, $buyer),
        ));
    }

    private function notifyFailed(Settlement $settlement, bool $final): void
    {
        $this->mailBuyer($settlement, fn (Tenant $buyer, string $to) => Mail::to($to)->send(
            new SettlementFailedMail($settlement, $buyer, $final),
        ));
    }

    /**
     * Schickt eine Meldung an den Kaeufer hinter dem Settlement. Ein fehlender
     * Empfaenger darf den Einzug nicht scheitern lassen -- die Buchung steht
     * bereits.
     *
     * @param  callable(Tenant, string): void  $send
     */
    private function mailBuyer(Settlement $settlement, callable $send): void
    {
        $buyer = $settlement->buyer();

        if (! $buyer instanceof Tenant) {
            return;
        }

        // `value()` statt `first()`: Die Beziehung traegt ein eigenes
        // Pivot-Model, weshalb der statischen Analyse hier kein User-Objekt
        // zur Verfuegung steht. Gebraucht wird ohnehin nur die Adresse.
        $email = (string) ($buyer->users()->value('users.email') ?? '');

        if ($email === '') {
            Log::warning('Postpaid: Meldung zum Einzug ohne Empfaenger.', [
                'settlement_id' => $settlement->getKey(),
                'tenant_id' => $buyer->getKey(),
            ]);

            return;
        }

        try {
            $send($buyer, $email);
        } catch (Throwable $exception) {
            Log::warning('Postpaid: Meldung zum Einzug konnte nicht verschickt werden.', [
                'settlement_id' => $settlement->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret_key'));
    }
}
