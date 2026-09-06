<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\ConditionOperator;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelCondition>
 */
class FunnelConditionFactory extends Factory
{
    protected $model = FunnelCondition::class;

    /**
     * Quellfrage und Zielschritt gehoeren immer zum selben Funnel wie die Regel.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'source_question_id' => fn (array $attributes): int => FunnelQuestion::factory()->create([
                'step_id' => FunnelStep::factory()->create(['funnel_id' => $attributes['funnel_id']])->id,
            ])->id,
            'operator' => ConditionOperator::EQUALS,
            'value' => ['ja'],
            'target_step_id' => fn (array $attributes): int => FunnelStep::factory()->create([
                'funnel_id' => $attributes['funnel_id'],
            ])->id,
            'priority' => 0,
        ];
    }

    public function withOperator(ConditionOperator $operator): static
    {
        return $this->state(fn (): array => ['operator' => $operator]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (): array => ['priority' => $priority]);
    }
}
