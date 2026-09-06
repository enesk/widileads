<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ein Lead in der API (FB-032, genutzt ab FB-030d).
 *
 * Die Kontaktdaten kommen ausschliesslich ueber `contactFor()` und sind damit
 * bereits entschieden -- maskiert oder Klartext. Diese Klasse trifft die
 * Entscheidung nicht und kennt die Maskierregeln nicht.
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lead $lead */
        $lead = $this->resource;

        $viewer = $request->user();

        return [
            'id' => $lead->id,
            'state' => $lead->lead_state->value,
            'score' => $lead->score,
            'result_key' => $lead->result_key,
            'created_at' => $lead->created_at?->toIso8601String(),
            'contact' => $lead->contactFor($viewer instanceof User ? $viewer : null)->toArray(),
        ];
    }
}
