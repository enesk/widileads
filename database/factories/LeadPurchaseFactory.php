<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\PurchaseStatus;
use App\Constants\TenantType;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Services\Wallet\PurchaseService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadPurchase>
 */
class LeadPurchaseFactory extends Factory
{
    protected $model = LeadPurchase::class;

    /**
     * Seit LP-WALLET-003 traegt der Kaufbeleg auch die Geldseite: Verkaeufer,
     * Provisionsaufteilung und Stand des Geldes. Der Verkaeufer wird aus dem
     * Lead abgeleitet -- er ist der Mandant, dem der Lead gehoert, und ein
     * abweichender Wert waere ein Beleg, den es so nie gaebe.
     *
     * Vorgabe ist `captured`: Ein von Hand gestellter Beleg beschreibt in aller
     * Regel einen abgeschlossenen Kauf. Eine offene Reservierung stellt man mit
     * dem Zustand reserved().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'buyer_tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'seller_tenant_id' => fn (array $attributes) => Lead::query()
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereKey($attributes['lead_id'])
                ->value('tenant_id')
                ?? Tenant::factory()->state(['type' => TenantType::OPERATOR]),
            'price_cents' => 1500,
            'commission_percent' => fn (): float => (float) config('wallet.commission_percent'),
            'commission_cents' => fn (array $attributes): int => PurchaseService::commissionCents(
                (int) $attributes['price_cents'],
                (float) $attributes['commission_percent'],
            ),
            'seller_net_cents' => fn (array $attributes): int => (int) $attributes['price_cents']
                - (int) $attributes['commission_cents'],
            'status' => PurchaseStatus::CAPTURED,
            'currency' => (string) config('wallet.currency'),
            'purchased_at' => now(),
            'reserved_at' => now(),
            'captured_at' => now(),
        ];
    }

    /**
     * Ein Kauf, dessen Geld noch geblockt ist -- der Lead ist gekauft, aber
     * noch nicht abgerechnet.
     */
    public function reserved(): self
    {
        return $this->state(fn (): array => [
            'status' => PurchaseStatus::RESERVED,
            'captured_at' => null,
        ]);
    }

    /**
     * Ein abgerechneter Kauf, der nach anerkannter Reklamation erstattet wurde.
     * Der Erloes ist dem Verkaeufer damit wieder abgegangen.
     */
    public function refunded(): self
    {
        return $this->state(fn (): array => [
            'status' => PurchaseStatus::REFUNDED,
            'refunded_at' => now(),
        ]);
    }
}
