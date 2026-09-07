<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\PublicV1;

use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Struktur eines veroeffentlichten Funnels fuer fremde Frontends (FB-026).
 *
 * Ausgeliefert wird der Snapshot der veroeffentlichten Fassung, unveraendert im
 * dokumentierten Format (docs/funnel-builder/snapshot-format.md). Er enthaelt
 * von Haus aus keine Datenbank-IDs -- adressiert wird ueber Schrittposition und
 * Feldschluessel. Genau deshalb kann er nach aussen gehen, ohne dass etwas
 * gefiltert werden muesste.
 *
 * @mixin Funnel
 */
class FunnelSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;
        $snapshot = $version === null ? [] : (array) $version->snapshot;

        return [
            'funnel' => $snapshot['funnel'] ?? [],
            'version' => $version?->version,
            'steps' => $snapshot['steps'] ?? [],
            'conditions' => $snapshot['conditions'] ?? [],
            'results' => $snapshot['results'] ?? [],
            'theme' => $snapshot['theme'] ?? null,
        ];
    }
}
