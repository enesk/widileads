<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\TenantType;
use App\Models\BuyerProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerProfile>
 */
class BuyerProfileFactory extends Factory
{
    protected $model = BuyerProfile::class;

    /**
     * Ein frisches Profil ohne Einschraenkung -- so entsteht es auch in der
     * Anwendung, damit ein neuer Kaeufer nicht vor einem leeren Marktplatz steht.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'funnel_ids' => [],
            'postal_prefixes' => [],
            'answer_filters' => [],
            'min_score' => null,
            'daily_limit' => null,
            'auto_buy' => false,
            'notify_email' => null,
        ];
    }
}
