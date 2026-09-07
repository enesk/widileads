<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Constants\FunnelStatus;
use App\Constants\SessionEventType;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\QuestionTypes\QuestionTypeRegistry;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;
use App\Funnel\Snapshots\StepSnapshot;
use App\Models\Funnel;
use App\Models\PublicSession;
use App\Services\PublicSessionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Der Ablauf einer Funnel-Strecke, unabhaengig von der Oberflaeche (FB-026).
 *
 * Bis hierher lag er in der Livewire-Komponente. Die Headless-API braucht
 * denselben Ablauf -- ihn dort nachzubauen hiesse, zwei Runtimes zu pflegen,
 * die zwangslaeufig auseinanderlaufen: Ein fremdes Frontend saehe dann eine
 * andere Verzweigung oder eine andere Punktzahl als unsere eigene Strecke.
 * Deshalb liegt der Ablauf hier, und beide Oberflaechen sind duenn.
 *
 * Der Dienst kennt keine Sitzungscookies, keine Views und keine Antwortformate.
 */
class FunnelRunService
{
    public function __construct(
        private readonly PublicSessionService $sessions,
        private readonly QuestionTypeRegistry $questionTypes,
        private readonly StepResolver $stepResolver,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly ResultResolver $resultResolver,
        private readonly SpamGuard $spamGuard,
        private readonly SubmissionReceiver $submissionReceiver,
    ) {}

    /**
     * Ist dieser Funnel oeffentlich auslieferbar?
     */
    public function isRunnable(Funnel $funnel): bool
    {
        return $funnel->current_version_id !== null
            && $funnel->status !== FunnelStatus::DRAFT;
    }

    public function isArchived(Funnel $funnel): bool
    {
        return $funnel->status === FunnelStatus::ARCHIVED;
    }

    public function snapshotOf(Funnel $funnel): FunnelSnapshot
    {
        $version = $funnel->currentVersion;

        return FunnelSnapshot::fromArray($version === null ? [] : $version->snapshot);
    }

    /**
     * Sitzung beginnen oder fortsetzen und den Aufruf protokollieren.
     *
     * @param  array<string, string|null>  $origin  Herkunft aus FB-022
     */
    public function startOrResume(Funnel $funnel, ?string $sessionToken, array $origin = []): PublicSession
    {
        $snapshot = $this->snapshotOf($funnel);
        $session = $this->sessions->startOrResume($funnel->currentVersion, $sessionToken, $origin);

        $currentStep = $session->current_step ?? ($snapshot->stepPositions()[0] ?? 0);

        $this->sessions->record($session, SessionEventType::STEP_VIEW, $currentStep);
        $this->sessions->saveProgress($session, $session->answers ?? [], $currentStep);

        return $session->refresh();
    }

    /**
     * Antworten eines Schritts pruefen, vereinheitlichen und den Weg fortsetzen.
     *
     * @param  array<string, mixed>  $answers  bereits gegebene und neue Antworten
     *
     * @throws ValidationException
     */
    public function submitStep(PublicSession $session, FunnelSnapshot $snapshot, array $answers): StepOutcome
    {
        $step = $this->stepAt($snapshot, (int) $session->current_step);

        if ($step !== null) {
            $this->validate($step, $answers);
            $answers = $this->normalize($step, $answers);
        }

        $score = $this->scoreCalculator->calculate($snapshot, $answers);
        $next = $this->stepResolver->next($snapshot, $answers, (int) $session->current_step, $score);

        $this->sessions->record($session, SessionEventType::STEP_COMPLETE, $session->current_step);
        $this->sessions->saveProgress($session, $answers, $next ?? $session->current_step);

        if ($next === null) {
            return StepOutcome::finished($this->resultResolver->resolve($snapshot, $score), $score);
        }

        $this->sessions->record($session, SessionEventType::STEP_VIEW, $next);

        // Vor dem Kontaktschritt steht der Ergebnis-Screen: Der Endkunde soll
        // wissen, wofuer er seine Daten hergibt, bevor er sie eingibt.
        if ($this->isContactStep($snapshot, $next) && $snapshot->results !== []) {
            $result = $this->resultResolver->resolve($snapshot, $score);

            if ($result instanceof ResultSnapshot) {
                return StepOutcome::result($result, $score);
            }
        }

        return StepOutcome::nextStep($this->stepAt($snapshot, $next) ?? $step);
    }

    /**
     * Anfrage abschliessen: pruefen, Sitzung schliessen, uebergeben.
     *
     * @param  array<string, mixed>  $answers
     * @param  string|null  $honeypotValue  Inhalt des versteckten Felds (FB-023)
     */
    public function submit(
        PublicSession $session,
        Funnel $funnel,
        FunnelSnapshot $snapshot,
        array $answers,
        ?string $honeypotValue = null,
    ): SubmissionOutcome {
        $assessment = $this->spamGuard->assess($session, $answers, $honeypotValue, $funnel->getKey());
        $session->forceFill(['spam_signals' => $assessment->toArray()])->save();

        $score = $this->scoreCalculator->calculate($snapshot, $answers);
        $result = $this->resultResolver->resolve($snapshot, $score);

        if ($assessment->blocksSubmission()) {
            // Die Sitzung bleibt offen: Wer zu Unrecht getroffen wurde, kann es
            // spaeter erneut versuchen, ohne von vorn anzufangen.
            return new SubmissionOutcome(false, $result, $score, $assessment);
        }

        $this->sessions->saveProgress($session, $answers, $session->current_step);
        $this->sessions->complete($session);

        $this->submissionReceiver->receive(new FunnelSubmissionData(
            publicToken: $funnel->public_token,
            funnelVersionId: (int) $funnel->current_version_id,
            publicSessionId: (int) $session->getKey(),
            answers: $answers,
            score: $score,
            resultKey: $result?->key,
            spamSignals: $assessment->toArray(),
            duplicateOfLeadId: $assessment->duplicateOfLeadId,
        ));

        return new SubmissionOutcome(true, $result, $score, $assessment);
    }

    /**
     * Regeln eines Schritts, wie sie beide Oberflaechen brauchen.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function rulesFor(StepSnapshot $step): array
    {
        $rules = [];

        foreach ($step->questions as $question) {
            $handler = $this->questionTypes->for($question->questionType());

            if (! $handler->expectsAnswer()) {
                continue;
            }

            $rules['answers.'.$question->fieldKey] = $handler->rules($question);
        }

        return $rules;
    }

    /**
     * Beschriftungen der Fragen, damit Fehlermeldungen den Text nennen, den der
     * Endkunde gelesen hat -- nicht den Feldschluessel.
     *
     * @return array<string, string>
     */
    public function attributesFor(StepSnapshot $step): array
    {
        $attributes = [];

        foreach ($step->questions as $question) {
            $attributes['answers.'.$question->fieldKey] = $question->label;
        }

        return $attributes;
    }

    public function stepAt(FunnelSnapshot $snapshot, int $position): ?StepSnapshot
    {
        foreach ($snapshot->steps as $step) {
            if ($step->position === $position) {
                return $step;
            }
        }

        return null;
    }

    public function isContactStep(FunnelSnapshot $snapshot, int $position): bool
    {
        $contactStep = $snapshot->funnel['contact_step_position'] ?? null;

        return $contactStep !== null && (int) $contactStep === $position;
    }

    /**
     * @param  array<string, mixed>  $answers
     *
     * @throws ValidationException
     */
    private function validate(StepSnapshot $step, array $answers): void
    {
        $rules = $this->rulesFor($step);

        if ($rules === []) {
            return;
        }

        Validator::make(['answers' => $answers], $rules, [], $this->attributesFor($step))->validate();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function normalize(StepSnapshot $step, array $answers): array
    {
        foreach ($step->questions as $question) {
            $handler = $this->questionTypes->for($question->questionType());

            $answers[$question->fieldKey] = $handler->normalize($answers[$question->fieldKey] ?? null, $question);
        }

        return $answers;
    }
}
