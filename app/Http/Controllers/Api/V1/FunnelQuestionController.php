<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelQuestionWriteRequest;
use App\Http\Resources\Api\V1\FunnelQuestionResource;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fragen eines Schritts (FB-030b).
 */
class FunnelQuestionController extends Controller
{
    public function index(Funnel $funnel, FunnelStep $step): AnonymousResourceCollection
    {
        return FunnelQuestionResource::collection($step->questions()->get());
    }

    public function store(FunnelQuestionWriteRequest $request, Funnel $funnel, FunnelStep $step): JsonResponse
    {
        $question = $step->questions()->create([
            ...$request->validated(),
            'funnel_id' => $funnel->getKey(),
            'position' => $request->integer('position') ?: $this->nextPosition($step),
        ]);

        return FunnelQuestionResource::make($question)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Funnel $funnel, FunnelStep $step, FunnelQuestion $question): FunnelQuestionResource
    {
        return FunnelQuestionResource::make($question);
    }

    public function update(
        FunnelQuestionWriteRequest $request,
        Funnel $funnel,
        FunnelStep $step,
        FunnelQuestion $question,
    ): FunnelQuestionResource {
        $question->update($request->validated());

        return FunnelQuestionResource::make($question->fresh() ?? $question);
    }

    public function destroy(Funnel $funnel, FunnelStep $step, FunnelQuestion $question): JsonResponse
    {
        $question->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function nextPosition(FunnelStep $step): int
    {
        return (int) $step->questions()->max('position') + 1;
    }
}
