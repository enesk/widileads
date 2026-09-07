<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine Fassung samt Snapshot (FB-030c, Schema `FunnelVersionWithSnapshot`).
 *
 * Der Snapshot hat dasselbe Format wie GET /funnels/{funnel}/structure und
 * laesst sich unveraendert in einen anderen Funnel einspielen -- deshalb wird
 * er hier unveraendert durchgereicht.
 *
 * @mixin FunnelVersion
 */
class FunnelVersionWithSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'note' => $this->note,
            'published_at' => $this->published_at?->toIso8601String(),
            'published_by' => $this->publisher?->name,
            'snapshot' => $this->snapshot,
        ];
    }
}
