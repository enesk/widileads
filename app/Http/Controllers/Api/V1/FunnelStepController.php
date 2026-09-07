<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelStepWriteRequest;
use App\Http\Resources\Api\V1\FunnelStepResource;
use App\Models\Funnel;
use App\Models\FunnelStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schritte eines Funnels (FB-030b).
 */
class FunnelStepController extends Controller
{
    public function index(Funnel $funnel): AnonymousResourceCollection
    {
        return FunnelStepResource::collection($funnel->steps()->get());
    }

    public function store(FunnelStepWriteRequest $request, Funnel $funnel): JsonResponse
    {
        $step = $funnel->steps()->create([
            ...$request->validated(),
            'position' => $request->integer('position') ?: $this->nextPosition($funnel),
        ]);

        return FunnelStepResource::make($step)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel, FunnelStep $step): FunnelStepResource
    {
        return FunnelStepResource::make($step);
    }

    public function update(FunnelStepWriteRequest $request, Funnel $funnel, FunnelStep $step): FunnelStepResource
    {
        $step->update($request->validated());

        return FunnelStepResource::make($step->fresh() ?? $step);
    }

    public function destroy(Funnel $funnel, FunnelStep $step): JsonResponse
    {
        $step->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function nextPosition(Funnel $funnel): int
    {
        return (int) $funnel->steps()->max('position') + 1;
    }
}
