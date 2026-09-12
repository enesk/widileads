<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PaymentMode;
use App\Constants\WalletOwnerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Ein Geldtopf des Marktplatzes (LP-WALLET-004).
 *
 * Ein Wallet gehoert einer Rolle, nicht einem Mandanten: Derselbe Mandant kann
 * als Kaeufer und als Verkaeufer auftreten und haelt dann zwei getrennte Toepfe
 * mit getrennten Ledgern (WalletOwnerType). Das Wallet der Plattform hat keinen
 * Besitzer und traegt `owner_id = null`.
 *
 * `balance_cents` und `reserved_cents` sind fortgeschriebene Salden; die
 * Wahrheit bleibt das Journal in `wallet_transactions`. Geschrieben werden
 * beide Spalten ausschliesslich vom WalletService (LP-WALLET-005) -- jede
 * Aenderung eines Saldos hat dort eine Buchung als Beleg.
 *
 * Abweichung von der Ticketvorgabe "owner() morphTo": `owner_type` traegt die
 * Rolle (`buyer`, `seller`, `platform`) und nicht einen Klassennamen, und der
 * Fremdschluessel zeigt fest auf `tenants`. Ein echtes morphTo wuerde die Rolle
 * als Klassennamen aufloesen wollen; ein globaler Morph-Map-Alias fuer Tenant
 * wuerde dagegen bestehende Morph-Spalten (audit_logs) umschreiben. Deshalb ist `owner()` ein BelongsTo auf Tenant, die
 * Rolle liest man ueber `owner_type`.
 *
 * @property int $id
 * @property WalletOwnerType $owner_type
 * @property int|null $owner_id
 * @property string $currency
 * @property int $balance_cents
 * @property int $reserved_cents
 * @property PaymentMode $payment_mode
 * @property int $credit_limit_cents
 * @property bool $purchase_blocked
 * @property Carbon|null $postpaid_enabled_at
 * @property int|null $postpaid_enabled_by
 * @property Carbon|null $postpaid_disabled_at
 * @property string|null $postpaid_disabled_reason
 * @property-read int $available_cents
 * @property-read int $open_amount_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Wallet extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'currency',
        'balance_cents',
        'reserved_cents',
        'payment_mode',
        'credit_limit_cents',
        'purchase_blocked',
        'postpaid_enabled_at',
        'postpaid_enabled_by',
        'postpaid_disabled_at',
        'postpaid_disabled_reason',
    ];

    /**
     * Der Mandant, dem dieses Wallet gehoert. Beim Plattform-Wallet null.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'owner_id');
    }

    /**
     * Das Journal dieses Wallets, neueste Buchung zuerst.
     *
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('created_at');
    }

    /**
     * Auszahlungsanforderungen aus diesem Topf, neueste zuerst
     * (LP-WALLET-010). Nur Verkaeufer-Wallets haben welche.
     *
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class)->latest('requested_at');
    }

    /**
     * Die hinterlegten Zahlungsmittel dieses Wallets (LP-POSTPAID-003),
     * neueste zuerst. Nur Kauf-Wallets haben welche.
     *
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->latest('created_at');
    }

    /**
     * Das Mittel, mit dem der Einzug versucht wird: das als Standard markierte
     * und noch einsatzbereite.
     *
     * Bewusst ueber eine Beziehung und nicht ueber eine Spalte am Wallet: Die
     * Migration verzichtet auf einen Unique-Index (MariaDB kennt keinen
     * partiellen), dass es hoechstens ein aktives Standardmittel gibt, sichert
     * der PostpaidService (LP-POSTPAID-005). Diese Beziehung liefert deshalb
     * das zuletzt angelegte -- ein aelterer Rest kann so nie eingezogen werden.
     *
     * @return HasOne<PaymentMethod, $this>
     */
    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)
            ->where('is_default', true)
            ->where('status', PaymentMethod::STATUS_ACTIVE)
            ->latestOfMany();
    }

    /**
     * Die Einzuege dieses Wallets (LP-POSTPAID-003), neueste zuerst.
     *
     * @return HasMany<Settlement, $this>
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class)->latest('created_at');
    }

    /**
     * Der laufende Einzug, falls es einen gibt: offen oder beim Anbieter
     * unterwegs. Es darf hoechstens einen geben -- ein zweiter wuerde
     * denselben offenen Betrag ein zweites Mal einziehen.
     *
     * @return HasOne<Settlement, $this>
     */
    public function openSettlement(): HasOne
    {
        return $this->hasOne(Settlement::class)->unresolved()->latestOfMany();
    }

    /**
     * Die Antraege auf Freischaltung von Pay as you go, neueste zuerst
     * (LP-POSTPAID-003).
     *
     * @return HasMany<PostpaidApplication, $this>
     */
    public function postpaidApplications(): HasMany
    {
        return $this->hasMany(PostpaidApplication::class)->latest('requested_at');
    }

    /**
     * Kauft dieses Wallet gegen Kreditrahmen statt aus Guthaben?
     */
    public function isPostpaid(): bool
    {
        return $this->payment_mode === PaymentMode::POSTPAID;
    }

    /**
     * Frei verfuegbares Guthaben: was nicht schon fuer laufende Leadkaeufe
     * geblockt ist, zuzueglich des gewaehrten Kreditrahmens. Nur dieser Betrag
     * darf ausgegeben werden.
     *
     * Der Rahmen steht hier und nicht in einer Sonderpruefung des
     * WalletService, weil er genau dasselbe bedeutet wie Guthaben: Er ist der
     * Betrag, den der Kaeufer noch ausgeben darf. Bei einem Prepaid-Wallet ist
     * er 0, die Rechnung bleibt damit die alte.
     *
     * Nach einer Rueckstufung wird der Rahmen auf 0 gesetzt, nicht der Saldo
     * angehoben: Ein Kaeufer mit -120 EUR hat dann 0 verfuegbar und bleibt die
     * 120 EUR schuldig.
     *
     * @return Attribute<int, never>
     */
    protected function availableCents(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->balance_cents - $this->reserved_cents + $this->credit_limit_cents,
        );
    }

    /**
     * Der offene Betrag, den der Kaeufer der Plattform schuldet: der negative
     * Teil des Saldos, positiv ausgedrueckt (LP-POSTPAID-004).
     *
     * Genau dieser Betrag wird eingezogen (LP-POSTPAID-008) und im Portal als
     * "offen" angezeigt. Ein Wallet im Plus schuldet nichts, deshalb 0 statt
     * eines negativen Werts -- eine Forderung mit negativem Betrag waere eine
     * Gutschrift und wuerde beim Einzug Geld in die falsche Richtung bewegen.
     *
     * @return Attribute<int, never>
     */
    protected function openAmountCents(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, -$this->balance_cents),
        );
    }

    /**
     * Das Wallet einer Rolle. `$ownerId` ist nur beim Plattform-Wallet null.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForOwner(Builder $query, WalletOwnerType|string $ownerType, ?int $ownerId = null): void
    {
        $type = $ownerType instanceof WalletOwnerType ? $ownerType->value : $ownerType;

        $query->where('owner_type', $type)
            ->when($ownerId === null, fn (Builder $q) => $q->whereNull('owner_id'))
            ->when($ownerId !== null, fn (Builder $q) => $q->where('owner_id', $ownerId));
    }

    /**
     * Das Kauf-Wallet eines Mandanten. Legt es bei Bedarf an -- der Regelweg
     * ist der TenantObserver, dies ist der Rueckfallweg fuer Mandanten, die es
     * vor LP-WALLET schon gab.
     */
    public static function forBuyer(Tenant $buyer): self
    {
        return self::forTenant(WalletOwnerType::BUYER, $buyer);
    }

    /**
     * Das Verkaufs-Wallet eines Mandanten, siehe forBuyer().
     */
    public static function forSeller(Tenant $seller): self
    {
        return self::forTenant(WalletOwnerType::SELLER, $seller);
    }

    /**
     * Das Wallet der Plattform: Provisionen, Erstattungen, Korrekturen. Es gibt
     * genau eines, angelegt in der Migration und im PlatformWalletSeeder.
     */
    public static function forPlatform(): self
    {
        return self::firstOrCreate(
            [
                'owner_type' => WalletOwnerType::PLATFORM->value,
                'owner_id' => null,
            ],
            ['currency' => config('wallet.currency')],
        );
    }

    private static function forTenant(WalletOwnerType $ownerType, Tenant $tenant): self
    {
        return self::firstOrCreate(
            [
                'owner_type' => $ownerType->value,
                'owner_id' => $tenant->getKey(),
            ],
            ['currency' => config('wallet.currency')],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => WalletOwnerType::class,
            'owner_id' => 'integer',
            'balance_cents' => 'integer',
            'reserved_cents' => 'integer',
            'payment_mode' => PaymentMode::class,
            'credit_limit_cents' => 'integer',
            'purchase_blocked' => 'boolean',
            'postpaid_enabled_at' => 'datetime',
            'postpaid_enabled_by' => 'integer',
            'postpaid_disabled_at' => 'datetime',
        ];
    }
}
