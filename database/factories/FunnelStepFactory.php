<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunnelStep>
 */
class FunnelStepFactory extends Factory
{
    protected $model = FunnelStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'position' => 0,
            'title' => Str::title(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
