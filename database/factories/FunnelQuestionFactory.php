<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunnelQuestion>
 */
class FunnelQuestionFactory extends Factory
{
    protected $model = FunnelQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Wird aus dem Schritt abgeleitet, siehe FunnelQuestion::booted().
            'funnel_id' => null,
            'step_id' => FunnelStep::factory(),
            'position' => 0,
            // Fragetypen kommen als Enum mit FB-011; bis dahin ein einfacher Textwert.
            'type' => 'text',
            'field_key' => fake()->unique()->word(),
            'label' => Str::ucfirst(fake()->words(3, true)),
            'help_text' => null,
            'required' => true,
            'validation' => null,
            'meta' => null,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (): array => ['required' => false]);
    }
}
