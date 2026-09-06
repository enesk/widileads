<?php

namespace Database\Factories;

use App\Constants\TenantType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'uuid' => fake()->uuid(),
            'type' => TenantType::OPERATOR,
        ];
    }

    /**
     * Betreiber-Tenant: baut und veroeffentlicht Funnels.
     */
    public function operator(): static
    {
        return $this->state(fn (): array => ['type' => TenantType::OPERATOR]);
    }

    /**
     * Kaeufer-Tenant: kauft Leads ueber den Marktplatz.
     */
    public function buyer(): static
    {
        return $this->state(fn (): array => ['type' => TenantType::BUYER]);
    }
}
