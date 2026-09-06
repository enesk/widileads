<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\FunnelStatus;
use App\Models\Funnel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Funnel>
 */
class FunnelFactory extends Factory
{
    protected $model = Funnel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'tenant_id' => Tenant::factory(),
            'public_token' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => FunnelStatus::DRAFT,
            'lead_price' => null,
            'contact_step_position' => null,
            'settings' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => FunnelStatus::PUBLISHED]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => FunnelStatus::ARCHIVED]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->id]);
    }
}
