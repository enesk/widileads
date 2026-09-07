<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelConditionWriteRequest;
use App\Http\Resources\Api\V1\FunnelConditionResource;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verzweigungsregeln eines Funnels (FB-030b).
 */
class FunnelConditionController extends Controller
{
    public function index(Funnel $funnel): AnonymousResourceCollection
    {
        return FunnelConditionResource::collection($funnel->conditions()->orderByDesc('priority')->get());
    }

    public function store(FunnelConditionWriteRequest $request, Funnel $funnel): JsonResponse
    {
        $condition = $funnel->conditions()->create($request->validated());

        return FunnelConditionResource::make($condition)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel, FunnelCondition $condition): FunnelConditionResource
    {
        return FunnelConditionResource::make($condition);
    }

    public function update(
        FunnelConditionWriteRequest $request,
        Funnel $funnel,
        FunnelCondition $condition,
    ): FunnelConditionResource {
        $condition->update($request->validated());

        return FunnelConditionResource::make($condition->fresh() ?? $condition);
    }

    public function destroy(Funnel $funnel, FunnelCondition $condition): JsonResponse
    {
        $condition->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
