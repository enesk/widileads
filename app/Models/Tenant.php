<?php

namespace App\Models;

use App\Constants\TenantType;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property TenantType $type
 */
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TenantType::class,
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
}
