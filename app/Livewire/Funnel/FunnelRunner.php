<?php

declare(strict_types=1);

namespace App\Livewire\Funnel;

use App\Constants\FunnelStatus;
use App\Constants\SessionEventType;
use App\Dto\FunnelSubmissionData;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\QuestionTypes\QuestionTypeRegistry;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Runtime\OriginCollector;
use App\Funnel\Runtime\SpamAssessment;
use App\Funnel\Runtime\SpamGuard;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;
use App\Funnel\Snapshots\StepSnapshot;
use App\Models\Funnel;
use App\Models\PublicSession;
use App\Services\PublicSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
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
 * Der Fortschritt liegt seit FB-021 in public_sessions: Ein Reload, ein
 * Netzabbruch oder ein Geraetewechsel darf den Endkunden nicht von vorn
 * anfangen lassen -- jede abgebrochene Strecke ist eine verlorene Anfrage. Der
 * Sitzungstoken steht in einem Cookie je Funnel.
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

    /** Token der laufenden Sitzung (public_sessions.token). */
    public string $sessionToken = '';

    /**
     * Honigtopf: Das Feld ist im Formular per CSS versteckt und traegt einen
     * unverdaechtigen Namen. Ein Mensch sieht es nie, ein Bot fuellt es aus.
     */
    public string $website = '';

    /** Meldung, wenn die Einreichung nicht angenommen wurde (Rate-Limit). */
    public ?string $submissionBlockedReason = null;

    /** questions | result | contact | done */
    public string $phase = 'questions';

    public int $score = 0;

    public ?string $resultKey = null;

    private ?FunnelSnapshot $snapshot = null;

    private ?PublicSession $session = null;

    private ?Funnel $funnel = null;

    public function mount(string $token): void
    {
        $funnel = $this->findPublishedFunnel($token);
        $this->token = $token;

        if ($funnel->currentVersion === null || $this->snapshot()->steps === []) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $sessions = app(PublicSessionService::class);
        $session = $sessions->startOrResume(
            $funnel->currentVersion,
            $this->sessionTokenFromCookie(),
            app(OriginCollector::class)->collect(request()),
        );

        $this->session = $session;
        $this->sessionToken = $session->token;
        Cookie::queue($this->cookieName(), $session->token, $this->cookieLifetimeInMinutes());

        // Teilfortschritt aufnehmen -- der Endkunde macht dort weiter, wo er war.
        $this->answers = $session->answers ?? [];
        $this->currentStepPosition = $session->current_step ?? ($this->snapshot()->stepPositions()[0] ?? 0);

        $sessions->record($session, SessionEventType::STEP_VIEW, $this->currentStepPosition);
        $sessions->saveProgress($session, $this->answers, $this->currentStepPosition);
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

            $sessions = app(PublicSessionService::class);
            $sessions->record($this->session(), SessionEventType::STEP_COMPLETE, $this->currentStepPosition);
            $sessions->saveProgress($this->session(), $this->answers, $this->currentStepPosition);

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

    /**
     * Prueft die Einreichung und haelt das Ergebnis an der Sitzung fest.
     *
     * Nur das Rate-Limit stoppt hier etwas. Honeypot und Zeitfalle werden still
     * vermerkt und spaeter bewertet (FB-033) -- der Lead entsteht trotzdem, denn
     * eine zu Unrecht verworfene Anfrage ist verloren, eine zu Unrecht
     * angenommene laesst sich noch aussortieren.
     */
    private function assessSubmission(): SpamAssessment
    {
        $session = $this->session();

        $assessment = app(SpamGuard::class)->assess(
            $session,
            $this->answers,
            $this->website,
            $this->funnel()->getKey(),
        );

        $session->forceFill(['spam_signals' => $assessment->toArray()])->save();

        return $assessment;
    }

    public function isArchived(): bool
    {
        return $this->funnel()->status === FunnelStatus::ARCHIVED;
    }

    private function finish(): void
    {
        $assessment = $this->assessSubmission();

        if ($assessment->blocksSubmission()) {
            // Die Sitzung bleibt offen: Wer zu Unrecht getroffen wurde, kann es
            // spaeter erneut versuchen, ohne von vorn anzufangen.
            $this->submissionBlockedReason = __('runtime.errors.rate_limited');

            return;
        }

        $this->prepareResult();
        $this->phase = 'done';

        $sessions = app(PublicSessionService::class);
        $session = $this->session();

        $sessions->record($session, SessionEventType::STEP_COMPLETE, $this->currentStepPosition);
        $sessions->saveProgress($session, $this->answers, $this->currentStepPosition);
        $sessions->complete($session);

        app(SubmissionReceiver::class)->receive(new FunnelSubmissionData(
            publicToken: $this->token,
            funnelVersionId: (int) $this->funnel()->current_version_id,
            publicSessionId: (int) $session->getKey(),
            answers: $this->answers,
            score: $this->score,
            resultKey: $this->resultKey,
            spamSignals: $assessment->toArray(),
            duplicateOfLeadId: $assessment->duplicateOfLeadId,
        ));
    }

    private function goTo(int $stepPosition): void
    {
        $sessions = app(PublicSessionService::class);
        $sessions->record($this->session(), SessionEventType::STEP_COMPLETE, $this->currentStepPosition);

        $this->currentStepPosition = $stepPosition;
        $this->phase = $stepPosition === $this->contactStepPosition() ? 'contact' : 'questions';

        $sessions->record($this->session(), SessionEventType::STEP_VIEW, $stepPosition);
        $sessions->saveProgress($this->session(), $this->answers, $stepPosition);
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

    private function session(): PublicSession
    {
        return $this->session ??= PublicSession::query()->where('token', $this->sessionToken)->firstOrFail();
    }

    /**
     * Cookiename je Funnel: Wer mehrere Strecken parallel ausfuellt, soll sich
     * nicht selbst ueberschreiben.
     */
    private function cookieName(): string
    {
        return 'funnel_session_'.$this->token;
    }

    private function cookieLifetimeInMinutes(): int
    {
        return (int) config('funnel.public.abandon_after_minutes') * 24;
    }

    private function sessionTokenFromCookie(): ?string
    {
        $token = request()->cookie($this->cookieName());

        return is_string($token) && $token !== '' ? $token : null;
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
