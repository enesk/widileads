<?php

namespace App\Models;

use App\Constants\TenantType;
use App\Constants\WalletOwnerType;
use App\Observers\TenantObserver;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property TenantType $type
 * @property int $lead_price_cents Preisvorgabe des Verkaeufers je Lead (LP-WALLET-003)
 * @property string|null $commission_percent Abweichender Provisionssatz; null = config('wallet.commission_percent')
 * @property string|null $payout_iban Bankverbindung des Verkaeufers, verschluesselt (LP-WALLET-010)
 * @property-read string|null $payout_iban_last4
 */
#[ObservedBy(TenantObserver::class)]
class Tenant extends Model
{
    /**
     * API-Tokens gehoeren dem Tenant, nicht einem einzelnen Nutzer (FB-006):
     * ein Token ueberlebt den Weggang des Nutzers, der es angelegt hat, und
     * kommt nur an die Daten seines eigenen Tenants.
     */
    use HasApiTokens;

    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'uuid',
        'is_name_auto_generated',
        'created_by',
        'domain',
        'lead_price_cents',
        'commission_percent',
        'payout_iban',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TenantType::class,
            'lead_price_cents' => 'integer',
            // Verschluesselt in der Datenbank (LP-WALLET-010): ausgezahlt
            // werden kann nur mit der ganzen Nummer, angezeigt wird nirgends
            // mehr als payout_iban_last4.
            'payout_iban' => 'encrypted',
        ];
    }

    /**
     * Betreiber-Tenant: baut und veroeffentlicht Funnels.
     */
    public function isOperator(): bool
    {
        return $this->type === TenantType::OPERATOR;
    }

    /**
     * Kaeufer-Tenant: kauft Leads ueber den Marktplatz.
     */
    public function isBuyer(): bool
    {
        return $this->type === TenantType::BUYER;
    }

    /**
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeOperators(Builder $query): Builder
    {
        return $query->where('type', TenantType::OPERATOR);
    }

    /**
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeBuyers(Builder $query): Builder
    {
        return $query->where('type', TenantType::BUYER);
    }

    /**
     * Registrierung und Freischaltungsstand eines Kaeufer-Mandanten (FB-050).
     * Bei Betreiber-Mandanten immer null.
     *
     * @return HasOne<BuyerRegistration, $this>
     */
    public function buyerRegistration(): HasOne
    {
        return $this->hasOne(BuyerRegistration::class);
    }

    /**
     * Kaufkriterien dieses Kaeufer-Mandanten (FB-051). Bei Betreiber-Mandanten
     * immer null.
     *
     * @return HasOne<BuyerProfile, $this>
     */
    public function buyerProfile(): HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }

    /**
     * Ist dieser Mandant ein vom Plattform-Admin freigeschalteter Kaeufer?
     *
     * Der Typ allein genuegt seit FB-050 nicht: ein Kaeufer entsteht durch
     * Selbstregistrierung und ist bis zur Entscheidung des Plattform-Admins
     * gesperrt.
     */
    public function isApprovedBuyer(): bool
    {
        return $this->isBuyer() && ($this->buyerRegistration?->isApproved() ?? false);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Funnels dieses Mandanten (FB-028). Die Gegenrelation zu Funnel::tenant();
     * ohne sie gibt es keinen Einstieg in den Builder.
     *
     * @return HasMany<Funnel, $this>
     */
    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(TenantUser::class)->withPivot('id')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stripeData(): HasOne
    {
        return $this->hasOne(UserStripeData::class);
    }

    public function subscriptionProductMetadata()
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->getTenantSubscriptionProductMetadata($this);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Der Geldtopf, aus dem dieser Mandant Leads kauft (LP-WALLET-004).
     *
     * Bewusst kein morphOne: `wallets.owner_type` traegt die Rolle und nicht
     * einen Klassennamen, siehe Wallet. Der Rueckfallweg, der das Wallet bei
     * Bedarf anlegt, ist Wallet::forBuyer(); im Regelbetrieb legt es der
     * TenantObserver schon bei der Registrierung an.
     *
     * @return HasOne<Wallet, $this>
     */
    public function buyerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'owner_id')
            ->where('owner_type', WalletOwnerType::BUYER->value);
    }

    /**
     * Der Geldtopf, in dem die Einnahmen dieses Mandanten aus verkauften Leads
     * liegen (LP-WALLET-004). Grundlage der Auszahlung.
     *
     * @return HasOne<Wallet, $this>
     */
    public function sellerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'owner_id')
            ->where('owner_type', WalletOwnerType::SELLER->value);
    }

    /**
     * Der Geldtopf zur Hauptrolle dieses Mandanten: Kaeufer kaufen, alle
     * uebrigen verkaufen.
     *
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->isBuyer() ? $this->buyerWallet() : $this->sellerWallet();
    }

    /**
     * Die letzte Vierergruppe der hinterlegten IBAN -- der einzige Teil, den
     * Portal, Auszahlungsliste und Mails je zu sehen bekommen (LP-WALLET-010).
     *
     * @return Attribute<string|null, never>
     */
    protected function payoutIbanLast4(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $iban = $this->payout_iban;

                if (! is_string($iban) || $iban === '') {
                    return null;
                }

                return substr($iban, -4);
            },
        );
    }

    /**
     * Kann an diesen Verkaeufer ueberhaupt ueberwiesen werden?
     */
    public function hasPayoutIban(): bool
    {
        return is_string($this->payout_iban) && $this->payout_iban !== '';
    }
}
