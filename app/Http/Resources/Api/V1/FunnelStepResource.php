<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ein Schritt eines Funnels (FB-030b, Schema `FunnelStep`).
 *
 * @mixin FunnelStep
 */
class FunnelStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'title' => $this->title,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
