<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\LeadState;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lead_state' => LeadState::NEU,
            'settled_price' => null,
            'settled_at' => null,
        ];
    }

    /**
     * Lead in einem bestimmten Zustand.
     *
     * Nur fuer Tests gedacht: im Betrieb entsteht jeder Zustand ausser dem
     * Eingangszustand ausschliesslich ueber LeadStateService::transition().
     */
    public function inState(LeadState $state): self
    {
        return $this->state(fn (): array => ['lead_state' => $state]);
    }
}
