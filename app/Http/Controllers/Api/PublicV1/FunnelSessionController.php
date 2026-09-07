<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\PublicV1;

use App\Funnel\Runtime\FunnelRunService;
use App\Funnel\Runtime\OriginCollector;
use App\Funnel\Runtime\StepOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicV1\StoreSessionRequest;
use App\Http\Requests\Api\PublicV1\SubmitSessionRequest;
use App\Http\Requests\Api\PublicV1\UpdateAnswersRequest;
use App\Http\Resources\Api\PublicV1\SessionStateResource;
use App\Models\Funnel;
use App\Models\PublicSession;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitzungen eines fremden Frontends (FB-026).
 *
 * Der Controller bleibt duenn: Ablauf, Validierung, Wegfindung, Punktzahl und
 * Spam-Pruefung liegen im FunnelRunService -- demselben, den die eigene Strecke
 * benutzt. Ein fremdes Frontend nimmt damit denselben Weg wie unser eigenes,
 * statt eine zweite, langsam auseinanderlaufende Runtime zu bedienen.
 */
class FunnelSessionController extends Controller
{
    public function __construct(private readonly FunnelRunService $runService) {}

    public function store(StoreSessionRequest $request, Funnel $funnel): JsonResponse
    {
        abort_unless($this->runService->isRunnable($funnel), Response::HTTP_NOT_FOUND);

        $session = $this->runService->startOrResume(
            $funnel,
            $request->string('session_token')->value() ?: null,
            app(OriginCollector::class)->collect($request),
        );

        $snapshot = $this->runService->snapshotOf($funnel);
        $step = $this->runService->stepAt($snapshot, (int) $session->current_step);

        return (new SessionStateResource(
            $session,
            $step === null ? null : StepOutcome::nextStep($step),
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Antworten eines Schritts pruefen und den naechsten Schritt zurueckgeben.
     */
    public function updateAnswers(UpdateAnswersRequest $request, Funnel $funnel, PublicSession $session): SessionStateResource
    {
        $this->ensureSessionBelongsToFunnel($funnel, $session);

        $snapshot = $this->runService->snapshotOf($funnel);

        $outcome = $this->runService->submitStep(
            $session,
            $snapshot,
            [...($session->answers ?? []), ...$request->array('answers')],
        );

        return new SessionStateResource($session->refresh(), $outcome);
    }

    public function submit(SubmitSessionRequest $request, Funnel $funnel, PublicSession $session): JsonResponse
    {
        $this->ensureSessionBelongsToFunnel($funnel, $session);

        $snapshot = $this->runService->snapshotOf($funnel);
        $answers = [...($session->answers ?? []), ...$request->array('answers')];

        // Der letzte Schritt wird wie jeder andere geprueft -- sonst koennte ein
        // fremdes Frontend die Kontaktdaten ungeprueft einliefern.
        $step = $this->runService->stepAt($snapshot, (int) $session->current_step);

        if ($step !== null) {
            $this->runService->submitStep($session, $snapshot, $answers);
            $session->refresh();
            $answers = $session->answers ?? $answers;
        }

        $outcome = $this->runService->submit(
            $session,
            $funnel,
            $snapshot,
            $answers,
            $request->string('website')->value() ?: null,
        );

        if (! $outcome->accepted) {
            return response()->json([
                'message' => __('runtime.errors.rate_limited'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return (new SessionStateResource(
            $session->refresh(),
            StepOutcome::finished($outcome->result, $outcome->score),
        ))->response();
    }

    /**
     * Eine Sitzung gehoert zu genau einer Funnel-Fassung. Ohne diese Pruefung
     * koennte ein Token einer fremden Strecke untergeschoben werden.
     */
    private function ensureSessionBelongsToFunnel(Funnel $funnel, PublicSession $session): void
    {
        abort_unless(
            $session->funnel_version_id === $funnel->current_version_id && $session->completed_at === null,
            Response::HTTP_NOT_FOUND,
        );
    }
}
