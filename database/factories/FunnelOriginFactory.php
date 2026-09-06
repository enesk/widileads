<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelOrigin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelOrigin>
 */
class FunnelOriginFactory extends Factory
{
    protected $model = FunnelOrigin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'origin' => 'https://'.fake()->unique()->domainName(),
        ];
    }
}
