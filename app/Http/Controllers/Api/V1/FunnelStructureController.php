<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Funnel\Snapshots\SnapshotBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelStructureRequest;
use App\Models\Funnel;
use App\Services\FunnelStructureReplacer;
use Illuminate\Http\JsonResponse;

/**
 * Komplette Struktur eines Funnels (FB-030b).
 *
 * Ein Aufruf statt eines Dutzends: Ein externes System kann einen ganzen Funnel
 * in einem Zug anlegen oder ersetzen. Gelesen wird dasselbe Format, das auch
 * der Snapshot benutzt -- wer es hier liest, kann es woanders einspielen.
 */
class FunnelStructureController extends Controller
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly FunnelStructureReplacer $structureReplacer,
    ) {}

    public function show(Funnel $funnel): JsonResponse
    {
        return response()->json(['data' => $this->structure($funnel)]);
    }

    public function update(FunnelStructureRequest $request, Funnel $funnel): JsonResponse
    {
        $this->structureReplacer->replace($funnel, $request->validated());

        return response()->json(['data' => $this->structure($funnel->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function structure(Funnel $funnel): array
    {
        $snapshot = $this->snapshotBuilder->build($funnel);

        // Die Struktur beschreibt den Aufbau, nicht die Veroeffentlichung --
        // Theme und Stammdaten des Funnels stehen an anderer Stelle.
        unset($snapshot['theme']);

        return $snapshot;
    }
}
