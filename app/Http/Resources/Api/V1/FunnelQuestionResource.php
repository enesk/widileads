<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine Frage (FB-030b, Schema `FunnelQuestion`).
 *
 * @mixin FunnelQuestion
 */
class FunnelQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_id' => $this->step_id,
            'position' => $this->position,
            'type' => $this->type->value,
            'field_key' => $this->field_key,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'required' => $this->required,
            'validation' => $this->validation,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
