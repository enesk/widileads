<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FunnelOptionWriteRequest;
use App\Http\Resources\Api\V1\FunnelOptionResource;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antwortoptionen einer Frage (FB-030b).
 */
class FunnelOptionController extends Controller
{
    public function index(Funnel $funnel, FunnelStep $step, FunnelQuestion $question): AnonymousResourceCollection
    {
        return FunnelOptionResource::collection($question->options()->get());
    }

    public function store(
        FunnelOptionWriteRequest $request,
        Funnel $funnel,
        FunnelStep $step,
        FunnelQuestion $question,
    ): JsonResponse {
        $option = $question->options()->create([
            ...$request->validated(),
            'position' => $request->integer('position') ?: $this->nextPosition($question),
        ]);

        return FunnelOptionResource::make($option)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        Funnel $funnel,
        FunnelStep $step,
        FunnelQuestion $question,
        FunnelOption $option,
    ): FunnelOptionResource {
        return FunnelOptionResource::make($option);
    }

    public function update(
        FunnelOptionWriteRequest $request,
        Funnel $funnel,
        FunnelStep $step,
        FunnelQuestion $question,
        FunnelOption $option,
    ): FunnelOptionResource {
        $option->update($request->validated());

        return FunnelOptionResource::make($option->fresh() ?? $option);
    }

    public function destroy(
        Funnel $funnel,
        FunnelStep $step,
        FunnelQuestion $question,
        FunnelOption $option,
    ): JsonResponse {
        $option->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function nextPosition(FunnelQuestion $question): int
    {
        return (int) $question->options()->max('position') + 1;
    }
}
