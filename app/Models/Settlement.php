<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PaymentMethodType;
use App\Constants\SettlementStatus;
use App\Observers\SettlementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Der Einzug des offenen Betrags eines Postpaid-Kaeufers (LP-POSTPAID-003).
 *
 * Ein Settlement fasst den zum Stichtag offenen Betrag eines Wallets zu einer
 * Forderung zusammen und zieht sie ueber den Zahlungsanbieter ein
 * (LP-POSTPAID-008). Es ist das Gegenstueck zu `payout_requests` auf der
 * Verkaeuferseite und haengt aus demselben Grund am Wallet.
 *
 * `amount_cents` ist immer positiv; die Gutschrift im Ledger entsteht erst,
 * wenn die Zahlung eingegangen ist -- als Buchung vom Typ `settlement`.
 *
 * @property int $id
 * @property int $wallet_id
 * @property int $amount_cents Immer positiv
 * @property SettlementStatus $status
 * @property string $trigger
 * @property int|null $payment_method_id
 * @property string|null $provider_payment_intent_id
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $prenotified_at
 * @property Carbon|null $charge_due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property string|null $invoice_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 * @property-read PaymentMethod|null $paymentMethod Nullable wie die Spalte: Der Kaeufer kann sein Mittel entfernt haben.
 */
#[ObservedBy(SettlementObserver::class)]
class Settlement extends Model
{
    /** Der woechentliche Einzugstermin. */
    public const TRIGGER_SCHEDULED = 'scheduled';

    /** Der offene Betrag hat die Schwelle ueberschritten. */
    public const TRIGGER_THRESHOLD = 'threshold';

    /** Vom Admin angestossen. */
    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'wallet_id',
        'amount_cents',
        'status',
        'trigger',
        'payment_method_id',
        'provider_payment_intent_id',
        'attempts',
        'next_attempt_at',
        'prenotified_at',
        'charge_due_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'invoice_reference',
    ];

    /**
     * Der Geldtopf, dessen Forderung eingezogen wird.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Das Mittel, ueber das eingezogen wird. Null, wenn der Kaeufer es
     * inzwischen entfernt hat -- die Forderung besteht dann weiter.
     *
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Der Kaeufer hinter dieser Forderung. Ueber das Wallet, weil das
     * Settlement an einem Geldtopf haengt und nicht am Mandanten.
     */
    public function buyer(): ?Tenant
    {
        return $this->wallet?->owner;
    }

    /**
     * Braucht dieser Einzug eine SEPA-Vorabankuendigung (LP-POSTPAID-014)?
     *
     * Die Pflicht haengt am Typ des Zahlungsmittels, nicht am Verfahren: Bei
     * Karte entfaellt sie. Ist gar kein Mittel (mehr) hinterlegt, gilt sie als
     * erforderlich -- eingezogen werden kann ohne Mittel ohnehin nicht, und
     * die vorsichtige Antwort ist hier die richtige.
     */
    public function requiresPrenotification(): bool
    {
        $type = $this->paymentMethod?->type;

        return $type === null || $type->requiresPrenotification();
    }

    /**
     * Darf jetzt belastet werden?
     *
     * Zwei Bedingungen, beide aus der Ankuendigungspflicht: Die Ankuendigung
     * muss raus sein (`prenotified_at`), und das dem Kaeufer angekuendigte
     * Belastungsdatum muss erreicht sein (`charge_due_at`). Ein frueherer
     * Einzug waere ein anderer als der angekuendigte und damit angreifbar.
     *
     * Braucht das Mittel keine Ankuendigung, ist der Einzug sofort moeglich.
     */
    public function mayBeCharged(?Carbon $at = null): bool
    {
        if (! $this->requiresPrenotification()) {
            return true;
        }

        if ($this->prenotified_at === null || $this->charge_due_at === null) {
            return false;
        }

        return $this->charge_due_at->lessThanOrEqualTo($at ?? Carbon::now());
    }

    /**
     * Offene Einzuege, deren Ankuendigung noch aussteht -- die Arbeitsliste
     * des ersten Schritts.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAwaitingPrenotification(Builder $query): void
    {
        $query->whereNull('prenotified_at')
            ->whereIn('status', [
                SettlementStatus::PENDING->value,
                SettlementStatus::RETRY_PENDING->value,
            ])
            ->whereHas('paymentMethod', function (Builder $method): void {
                $method->whereIn('type', array_map(
                    static fn (PaymentMethodType $type): string => $type->value,
                    array_filter(
                        PaymentMethodType::cases(),
                        static fn (PaymentMethodType $type): bool => $type->requiresPrenotification(),
                    ),
                ));
            });
    }

    /**
     * Offene Einzuege, die jetzt belastet werden duerfen -- die Arbeitsliste
     * des zweiten Schritts.
     *
     * Kartenzahlungen sind ohne Frist dabei, Lastschriften erst nach Ablauf
     * der angekuendigten Frist. Die Bedingung steht doppelt: hier als Abfrage
     * fuer die Liste und in mayBeCharged() als Pruefung je Datensatz -- die
     * Liste kann veralten, die Pruefung unmittelbar vor der Belastung nicht.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeChargeable(Builder $query, ?Carbon $at = null): void
    {
        $now = $at ?? Carbon::now();

        $query->whereIn('status', [
            SettlementStatus::PENDING->value,
            SettlementStatus::RETRY_PENDING->value,
        ])->where(function (Builder $inner) use ($now): void {
            $inner->where(function (Builder $announced) use ($now): void {
                $announced->whereNotNull('prenotified_at')
                    ->whereNotNull('charge_due_at')
                    ->where('charge_due_at', '<=', $now);
            })->orWhereHas('paymentMethod', function (Builder $method): void {
                $method->whereIn('type', array_map(
                    static fn (PaymentMethodType $type): string => $type->value,
                    array_filter(
                        PaymentMethodType::cases(),
                        static fn (PaymentMethodType $type): bool => ! $type->requiresPrenotification(),
                    ),
                ));
            });
        });
    }

    /**
     * Noch nicht entschiedene Einzuege: offen oder beim Anbieter unterwegs.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->whereIn('status', [
            SettlementStatus::PENDING->value,
            SettlementStatus::PROCESSING->value,
            SettlementStatus::RETRY_PENDING->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount_cents' => 'integer',
            'status' => SettlementStatus::class,
            'payment_method_id' => 'integer',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'prenotified_at' => 'datetime',
            'charge_due_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
