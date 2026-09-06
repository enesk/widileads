<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunnelResult>
 */
class FunnelResultFactory extends Factory
{
    protected $model = FunnelResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'min_score' => 0,
            'max_score' => 3,
            'title' => Str::ucfirst(fake()->words(3, true)),
            'body' => fake()->paragraph(),
            'cta_label' => null,
            'cta_url' => null,
            'show_contact_form' => true,
        ];
    }

    public function forScoreRange(int $minScore, int $maxScore): static
    {
        return $this->state(fn (): array => [
            'min_score' => $minScore,
            'max_score' => $maxScore,
        ]);
    }
}
