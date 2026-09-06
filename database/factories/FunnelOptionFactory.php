<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunnelOption>
 */
class FunnelOptionFactory extends Factory
{
    protected $model = FunnelOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::ucfirst(fake()->words(2, true));

        return [
            'question_id' => FunnelQuestion::factory(),
            'position' => 0,
            'label' => $label,
            'value' => Str::slug($label, '_'),
            'score' => null,
            'image_path' => null,
        ];
    }

    public function withScore(int $score): static
    {
        return $this->state(fn (): array => ['score' => $score]);
    }
}
