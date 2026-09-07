<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PublicV1;

use App\Funnel\Runtime\FunnelRunService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicV1\FunnelSnapshotResource;
use App\Models\Funnel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Struktur eines veroeffentlichten Funnels (FB-026).
 */
class FunnelStructureController extends Controller
{
    public function __construct(private readonly FunnelRunService $runService) {}

    public function show(Funnel $funnel): FunnelSnapshotResource
    {
        abort_unless($this->runService->isRunnable($funnel), Response::HTTP_NOT_FOUND);

        return new FunnelSnapshotResource($funnel->loadMissing('currentVersion'));
    }
}
