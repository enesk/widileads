<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stammdaten eines Funnels (FB-030b, Schema `Funnel`).
 *
 * Nach aussen wird ausschliesslich der `public_token` gezeigt; die fortlaufende
 * ID bleibt drinnen.
 *
 * @mixin Funnel
 */
class FunnelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_token' => $this->public_token,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'lead_price' => number_format($this->effectiveLeadPrice(), 2, '.', ''),
            'has_own_lead_price' => $this->lead_price !== null,
            'contact_step_position' => $this->contact_step_position,
            'settings' => $this->settings,
            'current_version' => $this->currentVersion?->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
