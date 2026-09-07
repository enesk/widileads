<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine Verzweigungsregel (FB-030b, Schema `FunnelCondition`).
 *
 * @mixin FunnelCondition
 */
class FunnelConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_question_id' => $this->source_question_id,
            'operator' => $this->operator->value,
            'value' => $this->value,
            'target_step_id' => $this->target_step_id,
            'evaluate_at_step_position' => $this->evaluate_at_step_position,
            'priority' => $this->priority,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
