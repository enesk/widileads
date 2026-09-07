<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\TenantType;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadPurchase>
 */
class LeadPurchaseFactory extends Factory
{
    protected $model = LeadPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'buyer_tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'price_cents' => 1500,
            'currency' => strtoupper((string) config('app.default_currency', 'EUR')),
            'purchased_at' => now(),
        ];
    }
}
