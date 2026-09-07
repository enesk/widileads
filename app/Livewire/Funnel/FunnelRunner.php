<?php

declare(strict_types=1);

namespace App\Livewire\Funnel;

use App\Constants\FunnelStatus;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\QuestionTypes\QuestionTypeRegistry;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Runtime\EmbedOriginPolicy;
use App\Funnel\Runtime\FunnelRunService;
use App\Funnel\Runtime\OriginCollector;
use App\Funnel\Runtime\StepOutcome;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\ResultSnapshot;
use App\Funnel\Snapshots\StepSnapshot;
use App\Models\Funnel;
use App\Models\PublicSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
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

    /** Laeuft die Strecke im iFrame einer fremden Seite? (FB-024) */
    public bool $embedded = false;

    /** Origin der einbettenden Seite -- Ziel der postMessage-Nachrichten. */
    public ?string $embedOrigin = null;

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

        $this->embedded = request()->boolean('embed');

        $policy = app(EmbedOriginPolicy::class);
        $this->embedOrigin = $policy->normalize(request()->query('origin'));

        // FB-025: Eingebettet wird nur von freigegebenen Seiten. Der Versuch
        // wird protokolliert -- sonst bliebe unsichtbar, wer den Funnel ohne
        // Wissen des Betreibers auf seiner Seite betreibt.
        if ($this->embedded && ! $policy->allows($funnel, $this->embedOrigin)) {
            $policy->recordRejection($funnel, $this->embedOrigin);

            abort(Response::HTTP_FORBIDDEN, __('runtime.errors.origin_not_allowed'));
        }

        $session = $this->runService()->startOrResume(
            $funnel,
            $this->sessionTokenFromCookie(),
            app(OriginCollector::class)->collect(request()),
        );

        $this->session = $session;
        $this->sessionToken = $session->token;
        Cookie::queue($this->cookieName(), $session->token, $this->cookieLifetimeInMinutes());

        // Teilfortschritt aufnehmen -- der Endkunde macht dort weiter, wo er war.
        $this->answers = $session->answers ?? [];
        $this->currentStepPosition = $session->current_step ?? ($this->snapshot()->stepPositions()[0] ?? 0);
        $this->phase = $this->runService()->isContactStep($this->snapshot(), $this->currentStepPosition)
            ? 'contact'
            : 'questions';
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
            'embedded' => $this->embedded,
            'embedOrigin' => $this->embedOrigin,
            'funnelToken' => $this->token,
        ]);
    }

    /**
     * Antworten des aktuellen Schritts pruefen und weitergehen.
     */
    public function submitStep(): void
    {
        $outcome = $this->advance();

        if ($outcome === null) {
            return;
        }

        $this->applyOutcome($outcome);
    }

    /**
     * Vom Ergebnis-Screen weiter zum naechsten Schritt (meist der Kontaktschritt).
     */
    public function continueAfterResult(): void
    {
        // Den Knopf gibt es nur im Ergebnis-Screen. Ohne diese Pruefung wuerde
        // ein zweiter Aufruf eine bereits abgeschlossene Strecke wieder in die
        // Fragen zurueckwerfen.
        if ($this->phase !== 'result') {
            return;
        }

        $snapshot = $this->snapshot();
        $step = $this->runService()->stepAt($snapshot, $this->currentStepPosition);

        if ($step === null) {
            $this->finish();

            return;
        }

        $this->phase = $this->runService()->isContactStep($snapshot, $this->currentStepPosition)
            ? 'contact'
            : 'questions';
    }

    /**
     * Der Kontaktschritt wird wie jeder andere geprueft -- er ist der letzte,
     * also endet die Strecke danach.
     */
    public function submitContact(): void
    {
        $this->submitStep();
    }

    /**
     * Einen Schritt abschicken. Validierungsfehler kommen aus dem
     * FunnelRunService und werden hier in Livewires Fehlerspeicher uebersetzt,
     * damit die Anzeige alte Meldungen aufraeumt.
     */
    /**
     * Ein archivierter Funnel zeigt einen Hinweis statt der Fragen (FB-020).
     */
    public function isArchived(): bool
    {
        return $this->runService()->isArchived($this->funnel());
    }

    private function advance(): ?StepOutcome
    {
        $this->resetErrorBag();

        try {
            return $this->runService()->submitStep($this->session(), $this->snapshot(), $this->answers);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return null;
        }
    }

    private function applyOutcome(StepOutcome $outcome): void
    {
        // Der Dienst hat die Sitzung bereits weitergeschaltet -- die Komponente
        // uebernimmt deren Stand, statt einen eigenen zu fuehren. Sonst zeigt
        // der Ergebnis-Screen auf einen Schritt, den die Sitzung laengst
        // verlassen hat, und der Weiter-Knopf landet im falschen Schritt.
        $session = $this->session()->refresh();

        $this->answers = $session->answers ?? $this->answers;
        $this->currentStepPosition = (int) ($session->current_step ?? $this->currentStepPosition);
        $this->score = $outcome->score;
        $this->resultKey = $outcome->result?->key;

        if ($outcome->isFinished()) {
            $this->finish();

            return;
        }

        $this->phase = match (true) {
            $outcome->phase === 'result' => 'result',
            $this->runService()->isContactStep($this->snapshot(), $this->currentStepPosition) => 'contact',
            default => 'questions',
        };
    }

    private function finish(): void
    {
        $outcome = $this->runService()->submit(
            $this->session(),
            $this->funnel(),
            $this->snapshot(),
            $this->answers,
            $this->website,
        );

        $this->score = $outcome->score;
        $this->resultKey = $outcome->result?->key;

        if (! $outcome->accepted) {
            // Die Sitzung bleibt offen: Wer zu Unrecht getroffen wurde, kann es
            // spaeter erneut versuchen, ohne von vorn anzufangen.
            $this->submissionBlockedReason = __('runtime.errors.rate_limited');

            return;
        }

        $this->phase = 'done';
    }

    private function runService(): FunnelRunService
    {
        return app(FunnelRunService::class);
    }

    private function currentResult(): ?ResultSnapshot
    {
        $snapshot = $this->snapshot();
        $score = app(ScoreCalculator::class)->calculate($snapshot, $this->answers);

        return app(ResultResolver::class)->resolve($snapshot, $score);
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
