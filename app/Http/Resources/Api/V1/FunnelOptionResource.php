<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine Antwortoption (FB-030b, Schema `FunnelOption`).
 *
 * @mixin FunnelOption
 */
class FunnelOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'position' => $this->position,
            'label' => $this->label,
            'value' => $this->value,
            'score' => $this->score,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
