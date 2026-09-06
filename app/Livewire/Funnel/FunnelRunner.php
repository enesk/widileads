<?php

declare(strict_types=1);

namespace App\Livewire\Funnel;

use App\Constants\FunnelStatus;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\QuestionTypes\QuestionTypeRegistry;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;
use App\Funnel\Snapshots\StepSnapshot;
use App\Models\Funnel;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oeffentliche Funnel-Strecke unter /f/{token} (FB-020).
 *
 * Gelesen wird ausschliesslich der Snapshot der veroeffentlichten Fassung --
 * nie die Live-Tabellen. Andernfalls wuerde eine Aenderung im Builder die
 * laufende Strecke veraendern, und der Endkunde beantwortete Fragen, die es zu
 * Beginn seiner Sitzung noch gar nicht gab.
 *
 * Der Ablauf: Fragen Schritt fuer Schritt (Weg ueber den StepResolver aus
 * FB-012), dann der Ergebnis-Screen (Punktzahl aus FB-013), dann der
 * Kontaktschritt, dann die Uebergabe an den SubmissionReceiver.
 *
 * Die Antworten liegen bewusst nur in der Komponente. Ihre Persistenz in
 * public_sessions ist FB-021.
 */
class FunnelRunner extends Component
{
    public string $token = '';

    /** Aktuelle Schrittposition innerhalb des Snapshots. */
    public int $currentStepPosition = 0;

    /**
     * Antworten des Endkunden, field_key => Wert.
     *
     * @var array<string, mixed>
     */
    public array $answers = [];

    /**
     * Bereits durchlaufene Schritte -- der tatsaechliche Weg, nicht die
     * gepflegte Reihenfolge.
     *
     * @var list<int>
     */
    public array $visitedStepPositions = [];

    /** questions | result | contact | done */
    public string $phase = 'questions';

    public int $score = 0;

    public ?string $resultKey = null;

    public ?string $startedAt = null;

    private ?FunnelSnapshot $snapshot = null;

    private ?Funnel $funnel = null;

    public function mount(string $token): void
    {
        $funnel = $this->findPublishedFunnel($token);

        $this->token = $token;
        $this->startedAt = now()->toIso8601String();
        $this->currentStepPosition = $this->snapshot()->stepPositions()[0] ?? 0;
        $this->visitedStepPositions = [$this->currentStepPosition];

        if ($funnel->currentVersion === null || $this->snapshot()->steps === []) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    public function render(): View
    {
        return view('livewire.funnel.funnel-runner', [
            'snapshot' => $this->snapshot(),
            'step' => $this->currentStep(),
            'result' => $this->currentResult(),
            'questionTypes' => app(QuestionTypeRegistry::class),
            'progress' => $this->progress(),
        ])->layout('components.layouts.funnel', [
            'title' => (string) ($this->snapshot()->funnel['name'] ?? ''),
        ]);
    }

    /**
     * Antworten des aktuellen Schritts pruefen und weitergehen.
     */
    public function submitStep(): void
    {
        $step = $this->currentStep();

        if ($step === null) {
            return;
        }

        $this->validateStep($step);
        $this->normalizeStep($step);

        $next = app(StepResolver::class)->next(
            $this->snapshot(),
            $this->answers,
            $this->currentStepPosition,
            $this->currentScore(),
        );

        // Vor dem Kontaktschritt kommt der Ergebnis-Screen: Der Endkunde soll
        // wissen, wofuer er seine Daten hergibt, bevor er sie eingibt.
        if ($this->shouldShowResultBefore($next)) {
            $this->prepareResult();
            $this->phase = 'result';

            return;
        }

        if ($next === null) {
            $this->finish();

            return;
        }

        $this->goTo($next);
    }

    /**
     * Vom Ergebnis-Screen weiter zum Kontaktschritt (oder ans Ende).
     */
    public function continueAfterResult(): void
    {
        $contactStepPosition = $this->contactStepPosition();

        if ($contactStepPosition !== null && $contactStepPosition !== $this->currentStepPosition) {
            $this->goTo($contactStepPosition);
            $this->phase = 'contact';

            return;
        }

        $next = app(StepResolver::class)->next(
            $this->snapshot(),
            $this->answers,
            $this->currentStepPosition,
            $this->currentScore(),
        );

        if ($next === null) {
            $this->finish();

            return;
        }

        $this->goTo($next);
        $this->phase = 'contact';
    }

    /**
     * Kontaktschritt absenden und die Einreichung uebergeben.
     */
    public function submitContact(): void
    {
        $step = $this->currentStep();

        if ($step !== null) {
            $this->validateStep($step);
            $this->normalizeStep($step);
        }

        $this->finish();
    }

    public function isArchived(): bool
    {
        return $this->funnel()->status === FunnelStatus::ARCHIVED;
    }

    private function finish(): void
    {
        $this->prepareResult();
        $this->phase = 'done';

        app(SubmissionReceiver::class)->receive(new FunnelSubmissionData(
            publicToken: $this->token,
            funnelVersionId: (int) $this->funnel()->current_version_id,
            answers: $this->answers,
            score: $this->score,
            resultKey: $this->resultKey,
            visitedStepPositions: array_values(array_unique($this->visitedStepPositions)),
            startedAt: $this->startedAt,
            submittedAt: now()->toIso8601String(),
        ));
    }

    private function goTo(int $stepPosition): void
    {
        $this->currentStepPosition = $stepPosition;
        $this->visitedStepPositions[] = $stepPosition;
        $this->phase = $stepPosition === $this->contactStepPosition() ? 'contact' : 'questions';
    }

    /**
     * Erst validieren, dann normalisieren -- die Regeln des Fragetyps kommen aus
     * FB-011 und gelten hier fuer die Snapshot-Fassung der Frage.
     */
    private function validateStep(StepSnapshot $step): void
    {
        $registry = app(QuestionTypeRegistry::class);
        $rules = [];
        $attributes = [];

        foreach ($step->questions as $question) {
            $handler = $registry->for($question->questionType());

            if (! $handler->expectsAnswer()) {
                continue;
            }

            $rules['answers.'.$question->fieldKey] = $handler->rules($question);
            $attributes['answers.'.$question->fieldKey] = $question->label;
        }

        if ($rules === []) {
            $this->resetErrorBag();

            return;
        }

        // Livewires eigene Validierung, nicht die Fassade: Sie raeumt den
        // Fehlerspeicher auf, sobald ein Schritt fehlerfrei durchgeht.
        $this->validate($rules, [], $attributes);
    }

    private function normalizeStep(StepSnapshot $step): void
    {
        $registry = app(QuestionTypeRegistry::class);

        foreach ($step->questions as $question) {
            $handler = $registry->for($question->questionType());
            $value = $this->answers[$question->fieldKey] ?? null;

            $this->answers[$question->fieldKey] = $handler->normalize($value, $question);
        }
    }

    private function shouldShowResultBefore(?int $nextStepPosition): bool
    {
        if ($this->phase !== 'questions' || $this->snapshot()->results === []) {
            return false;
        }

        $contactStepPosition = $this->contactStepPosition();

        // Ohne eigenen Kontaktschritt zeigt das Ende der Strecke das Ergebnis.
        if ($contactStepPosition === null) {
            return $nextStepPosition === null;
        }

        return $nextStepPosition === $contactStepPosition;
    }

    private function prepareResult(): void
    {
        $this->score = $this->currentScore();
        $this->resultKey = $this->currentResult()?->key;
    }

    private function currentScore(): int
    {
        return app(ScoreCalculator::class)->calculate($this->snapshot(), $this->answers);
    }

    private function currentResult(): ?ResultSnapshot
    {
        return app(ResultResolver::class)->resolve($this->snapshot(), $this->currentScore());
    }

    private function currentStep(): ?StepSnapshot
    {
        foreach ($this->snapshot()->steps as $step) {
            if ($step->position === $this->currentStepPosition) {
                return $step;
            }
        }

        return null;
    }

    private function contactStepPosition(): ?int
    {
        $position = $this->snapshot()->funnel['contact_step_position'] ?? null;

        return $position === null ? null : (int) $position;
    }

    /**
     * Fortschritt in Prozent -- gerechnet ueber die Schritte des Snapshots, denn
     * der tatsaechliche Weg steht erst am Ende fest.
     */
    private function progress(): int
    {
        $positions = $this->snapshot()->stepPositions();

        if ($positions === []) {
            return 100;
        }

        if ($this->phase === 'done') {
            return 100;
        }

        $index = array_search($this->currentStepPosition, $positions, true);

        return (int) round((((int) $index + 1) / count($positions)) * 100);
    }

    private function funnel(): Funnel
    {
        return $this->funnel ??= $this->findPublishedFunnel($this->token);
    }

    private function snapshot(): FunnelSnapshot
    {
        $version = $this->funnel()->currentVersion;

        return $this->snapshot ??= FunnelSnapshot::fromArray($version === null ? [] : $version->snapshot);
    }

    /**
     * Funnel zum oeffentlichen Token -- ohne Mandantenfilter, denn die Strecke
     * laeuft anonym und ohne angemeldeten Nutzer. Ein unbekannter oder nie
     * veroeffentlichter Token fuehrt zu 404; ein archivierter Funnel bekommt
     * eine Hinweisseite, wenn das in der Konfiguration so vorgesehen ist.
     */
    private function findPublishedFunnel(string $token): Funnel
    {
        $funnel = Funnel::query()
            ->withoutGlobalScopes()
            ->with('currentVersion')
            ->where('public_token', $token)
            ->first();

        if ($funnel === null || $funnel->current_version_id === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($funnel->status === FunnelStatus::ARCHIVED && ! config('funnel.runtime_public.show_notice_for_archived')) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($funnel->status === FunnelStatus::DRAFT) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->funnel = $funnel;

        return $funnel;
    }
}
