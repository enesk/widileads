<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelResultWriteRequest;
use App\Http\Resources\Api\V1\FunnelResultResource;
use App\Models\Funnel;
use App\Models\FunnelResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ergebnis-Screens eines Funnels (FB-030b).
 */
class FunnelResultController extends Controller
{
    public function index(Funnel $funnel): AnonymousResourceCollection
    {
        return FunnelResultResource::collection($funnel->results()->orderBy('min_score')->get());
    }

    public function store(FunnelResultWriteRequest $request, Funnel $funnel): JsonResponse
    {
        $result = $funnel->results()->create($request->validated());

        return FunnelResultResource::make($result)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel, FunnelResult $result): FunnelResultResource
    {
        return FunnelResultResource::make($result);
    }

    public function update(
        FunnelResultWriteRequest $request,
        Funnel $funnel,
        FunnelResult $result,
    ): FunnelResultResource {
        $result->update($request->validated());

        return FunnelResultResource::make($result->fresh() ?? $result);
    }

    public function destroy(Funnel $funnel, FunnelResult $result): JsonResponse
    {
        $result->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
