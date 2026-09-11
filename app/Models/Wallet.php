<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\WalletOwnerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read int $available_cents
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
     * Frei verfuegbares Guthaben: was nicht schon fuer laufende Leadkaeufe
     * geblockt ist. Nur dieser Betrag darf ausgegeben werden.
     */
    protected function availableCents(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->balance_cents - $this->reserved_cents,
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
        ];
    }
}
