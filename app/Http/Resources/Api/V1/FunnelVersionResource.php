<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FunnelVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eine veroeffentlichte Fassung (FB-030c, Schema `FunnelVersion`).
 *
 * Der Snapshot bleibt aussen vor -- er ist gross, und die Historie beantwortet
 * die Frage "wann und warum", nicht "wie sah es aus". Wer ihn braucht, liest
 * die einzelne Version.
 *
 * @mixin FunnelVersion
 */
class FunnelVersionResource extends JsonResource
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
        ];
    }
}
