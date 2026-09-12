<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PaymentMethodStatus;
use App\Constants\PaymentMethodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein hinterlegtes Zahlungsmittel eines Postpaid-Kaeufers (LP-POSTPAID-005).
 *
 * Die Zeile ist ein Verweis auf Stripe und kein Zahlungsmittel: Sie traegt die
 * Kennungen des Anbieters sowie die vier letzten Stellen und die Kartenmarke
 * zur Wiedererkennung im Portal. Vollstaendige IBAN oder Kartennummer beruehren
 * diese Anwendung nie -- sie entstehen im SetupIntent direkt bei Stripe.
 *
 * Geschrieben wird ausschliesslich vom App\Services\Payments\
 * PaymentMethodService. Wer hier von Hand `is_default` setzt, hat im
 * Zweifelsfall zwei Standardmittel je Wallet -- die Datenbank kann das nicht
 * verhindern (kein partieller Unique-Index in MariaDB), also tut es der
 * Service unter Transaktion.
 *
 * @property int $id
 * @property int $wallet_id
 * @property PaymentMethodType $type
 * @property string $provider
 * @property string $provider_customer_id
 * @property string $provider_payment_method_id
 * @property string|null $provider_mandate_id
 * @property string $last4
 * @property string|null $brand
 * @property Carbon|null $mandate_accepted_at
 * @property string|null $mandate_ip
 * @property bool $is_default
 * @property PaymentMethodStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 */
class PaymentMethod extends Model
{
    /**
     * Die Werte von PaymentMethodStatus noch einmal als Konstanten. Sie sind
     * fuer Stellen gedacht, die auf der Spalte arbeiten und nicht auf dem
     * Model -- Beziehungen mit `where`, Scopes, Abfragen im Admin. Dort steht
     * der rohe Spaltenwert, ein Enum-Case wuerde nicht vergleichen.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'wallet_id',
        'type',
        'provider',
        'provider_customer_id',
        'provider_payment_method_id',
        'provider_mandate_id',
        'last4',
        'brand',
        'mandate_accepted_at',
        'mandate_ip',
        'is_default',
        'status',
    ];

    /**
     * Der Geldtopf, fuer den dieses Mittel eingezogen wird.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Einsatzbereite Mittel -- nur mit ihnen darf ein Einzug versucht werden.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PaymentMethodStatus::ACTIVE->value);
    }

    /**
     * Bezeichnung fuer die Oberflaeche: "SEPA-Lastschrift •• 3000" bzw.
     * "Visa •• 4242". Die Kartenmarke steht bei SEPA nicht zur Verfuegung,
     * dort tritt der Name des Verfahrens an ihre Stelle.
     */
    public function label(): string
    {
        $name = $this->type === PaymentMethodType::CARD && filled($this->brand)
            ? ucfirst((string) $this->brand)
            : $this->type->label();

        return $name.' •• '.$this->last4;
    }

    /**
     * Liegt fuer dieses Mittel ein belegtes Lastschriftmandat vor? Bei Karten
     * immer true -- dort gibt es kein Mandat, das fehlen koennte.
     */
    public function hasValidMandate(): bool
    {
        if ($this->type !== PaymentMethodType::SEPA_DEBIT) {
            return true;
        }

        return $this->provider_mandate_id !== null && $this->mandate_accepted_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'type' => PaymentMethodType::class,
            'status' => PaymentMethodStatus::class,
            'is_default' => 'boolean',
            'mandate_accepted_at' => 'datetime',
        ];
    }
}
