<?php

declare(strict_types=1);

namespace App\Livewire\Buyer;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Constants\LeadContactStatus;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\CallerIdService;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\Twilio\OutboundCallFailed;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Anrufen und Versuchsverlauf zu einem gekauften Lead (FB-081, FB-084).
 *
 * Die Komponente entscheidet nichts: Ob ein Anruf zulaessig ist, prueft
 * ausschliesslich der CallService, ob ein Versuch zaehlt, der
 * AttemptClassifier. Was hier steht -- Sperrzeit, Zaehler, Frist -- ist die
 * Vorschau derselben Regeln aus config('lead_calls.*') und dient allein der
 * Nachvollziehbarkeit fuer den Kaeufer.
 *
 * Zwischen den Anfragen reist nur der Schluessel des Kaufbelegs. Weder Lead
 * noch Versuch werden als oeffentliche Eigenschaft gehalten: Ein Modell im
 * Livewire-Zustand muesste zwischen den Anfragen wiederhergestellt werden --
 * ohne den Kaeufer-Filter, der hier die einzige Zugangspruefung ist -- und
 * traegt mit `lead_number` genau die Rufnummer, die der Kaeufer vor der
 * Abrechnung nicht bekommen soll (FB-085).
 */
class LeadCallPanel extends Component
{
    #[Locked]
    public string $purchaseId = '';

    /**
     * Bis zu diesem Zeitpunkt fragt die Ansicht alle paar Sekunden nach.
     *
     * Ein Anruf braucht Sekunden, kein Dauerabo: Nach dem Start laeuft das
     * Fenster (siehe POLL_WINDOW_SECONDS) und endet, sobald der Versuch
     * abgeschlossen ist. Sonst haelt eine offene Lead-Seite die Anwendung
     * unbegrenzt beschaeftigt.
     */
    public ?string $pollUntil = null;

    /** Laenge des Nachfragefensters in Sekunden. */
    private const POLL_WINDOW_SECONDS = 120;

    /**
     * So lange gilt ein Versuch ohne Schlussmeldung als laufend -- derselbe
     * Wert, mit dem der CallService haengende Versuche verfallen laesst.
     */
    private const STALE_ATTEMPT_SECONDS = 3600;

    private ?LeadPurchase $loaded = null;

    public function mount(string $purchaseId): void
    {
        $this->purchaseId = $purchaseId;

        // Laedt und prueft -- ein fremder Beleg endet hier mit 404.
        $this->purchase();

        // Ein Anruf kann aus einem anderen Fenster laufen. Dann faengt die
        // Seite ihn mit an, statt einen abgeschlossenen Stand zu zeigen.
        if ($this->runningAttempt() !== null) {
            $this->openPollWindow();
        }
    }

    public function render(): View
    {
        return view('livewire.buyer.lead-call-panel');
    }

    /**
     * Startet den Anruf (FB-081). Twilio ruft zuerst den Mitarbeiter auf seiner
     * bestaetigten Nummer an und stellt ihn danach zum Lead durch.
     *
     * Der Name darf NICHT `call` lauten: `$wire.call(methode)` ist die
     * eingebaute Livewire-Schnittstelle im Browser. `wire:click="call"` ruft
     * sie ohne Argument auf, es geht `method: undefined` zum Server, und der
     * Klick endet in "Public method [undefined] not found on component".
     */
    public function startCall(CallService $calls): void
    {
        $user = $this->viewer();

        if (! $user instanceof User) {
            return;
        }

        try {
            $calls->start($this->purchase(), $user, $this->tenant());
        } catch (CallNotPossible $exception) {
            Notification::make()
                ->danger()
                ->title($exception->translated())
                ->send();

            $this->reload();

            return;
        } catch (OutboundCallFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio konnte den Anruf nicht starten.', [
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title(__('call.attempt.errors.provider_failed'))
                ->send();

            return;
        }

        $this->reload();
        $this->openPollWindow();
    }

    /**
     * Ziel des Nachfragens: neu laden und das Fenster schliessen, sobald der
     * Versuch abgeschlossen ist.
     */
    public function refreshStatus(): void
    {
        $this->reload();

        if ($this->runningAttempt() === null) {
            $this->pollUntil = null;
        }
    }

    public function shouldPoll(): bool
    {
        if ($this->pollUntil === null) {
            return false;
        }

        return Carbon::parse($this->pollUntil)->isFuture();
    }

    /**
     * Der Versuch, der gerade laeuft -- oder null.
     *
     * Ein Versuch ohne Schlussmeldung gilt nur so lange als laufend, wie er
     * frisch ist: Bleibt eine Meldung von Twilio aus, haengt die Ansicht sonst
     * fuer immer im Aufbau.
     */
    public function runningAttempt(): ?CallAttempt
    {
        return $this->purchase()->callAttempts
            ->first(static fn (CallAttempt $attempt): bool => $attempt->ended_at === null
                && $attempt->started_at instanceof Carbon
                && $attempt->started_at->gt(now()->subSeconds(self::STALE_ATTEMPT_SECONDS)));
    }

    /**
     * Der Satz waehrend des Aufbaus -- mit der Nummer, auf der der Mitarbeiter
     * gleich angerufen wird. Das ist seine eigene bestaetigte Nummer, nicht die
     * des Leads.
     */
    public function callingHint(): ?string
    {
        $attempt = $this->runningAttempt();

        if ($attempt === null) {
            return null;
        }

        return __('call.panel.calling', ['number' => $attempt->caller_number]);
    }

    public function presenter(): LeadPresenter
    {
        return new LeadPresenter($this->lead(), $this->viewer());
    }

    public function lead(): Lead
    {
        return $this->purchase()->lead;
    }

    /**
     * Der Stand der Erreichbarkeit als Abzeichen.
     *
     * @return array{label: string, color: string}
     */
    public function badge(): array
    {
        return match ($this->lead()->contact_status) {
            LeadContactStatus::BILLABLE => ['label' => __('call.panel.badge.billable'), 'color' => 'success'],
            LeadContactStatus::UNREACHABLE => ['label' => __('call.panel.badge.unreachable'), 'color' => 'danger'],
            default => ['label' => __('call.panel.badge.open'), 'color' => 'gray'],
        };
    }

    /**
     * Der Verlauf, neueste zuerst: Zeitpunkt, Ergebnis im Klartext und ob der
     * Versuch mitzaehlt.
     *
     * @return list<array{when: string, result: string, counted: bool, note: string|null}>
     */
    public function attempts(): array
    {
        return $this->purchase()->callAttempts->map(function (CallAttempt $attempt): array {
            $counted = $attempt->outcome?->counts() ?? true;

            return [
                'when' => $attempt->started_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '-',
                'result' => $this->resultLabel($attempt),
                'counted' => $counted,
                'note' => $counted ? null : $this->ignoreLabel($attempt),
            ];
        })->values()->all();
    }

    /** Gueltige Fehlversuche -- die Zahl, die das Regelwerk zaehlt. */
    public function failedCount(): int
    {
        return $this->purchase()->callAttempts
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->count();
    }

    public function requiredAttempts(): int
    {
        return (int) config('lead_calls.unreachable_attempts');
    }

    /**
     * Die Hinweisbox: unter welchen Bedingungen ein Lead als nicht erreichbar
     * gilt, und bis wann die Frist laeuft.
     */
    public function ruleHint(): string
    {
        $deadline = $this->lead()->deadline_at;

        return __('call.panel.rule_hint', [
            'attempts' => $this->requiredAttempts(),
            'days' => (int) config('lead_calls.unreachable_min_days'),
            'hours' => (int) config('lead_calls.retry_min_hours'),
            'deadline' => $deadline?->timezone(config('app.timezone'))->format('d.m.Y') ?? '-',
        ]);
    }

    /**
     * Darf der Knopf gedrueckt werden?
     *
     * Nur eine Vorschau fuer die Oberflaeche -- verbindlich entscheidet der
     * CallService in dem Moment, in dem der Anruf kommt.
     */
    public function canCall(): bool
    {
        return $this->blockedReason() === null;
    }

    /**
     * Warum gerade nicht angerufen werden kann -- oder null.
     *
     * Dieselben Gruende, die der CallService kennt, in derselben Reihenfolge:
     * entschiedener Lead, laufender Anruf, fehlende eigene Rufnummer,
     * Mindestabstand.
     */
    public function blockedReason(): ?string
    {
        if (! $this->lead()->isOpen()) {
            return __('call.attempt.errors.lead_resolved');
        }

        if ($this->runningAttempt() !== null) {
            return __('call.attempt.errors.already_running');
        }

        $user = $this->viewer();
        $callerId = $user instanceof User ? app(CallerIdService::class)->forUser($user) : null;

        if ($callerId === null || ! $callerId->isUsableAsCallerId()) {
            return __('call.attempt.errors.caller_id_missing');
        }

        $nextAllowedAt = $this->nextAllowedAt();

        if ($nextAllowedAt !== null) {
            return __('call.attempt.errors.too_soon', [
                'time' => $nextAllowedAt->timezone(config('app.timezone'))->format('H:i'),
            ]);
        }

        return null;
    }

    /**
     * Ab wann der naechste Versuch gilt -- oder null, wenn der Mindestabstand
     * bereits eingehalten ist.
     */
    public function nextAllowedAt(): ?Carbon
    {
        $hours = (int) config('lead_calls.retry_min_hours');

        if ($hours <= 0) {
            return null;
        }

        /** @var CallAttempt|null $last */
        $last = $this->purchase()->callAttempts
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->sortByDesc('started_at')
            ->first();

        if ($last === null || ! $last->started_at instanceof Carbon) {
            return null;
        }

        $nextAllowedAt = $last->started_at->copy()->addHours($hours);

        return $nextAllowedAt->isFuture() ? $nextAllowedAt : null;
    }

    /**
     * Das Ergebnis eines Versuchs im Klartext.
     *
     * Bewertet wird hier nichts -- gelesen werden `outcome`, `answered_by` und
     * die Dauer, also genau das, was der AttemptClassifier bereits entschieden
     * hat.
     */
    private function resultLabel(CallAttempt $attempt): string
    {
        if ($attempt->ended_at === null) {
            return __('call.panel.result.running');
        }

        if (str_starts_with((string) $attempt->answered_by, 'machine')) {
            return __('call.panel.result.voicemail');
        }

        $duration = (int) ($attempt->duration_seconds ?? 0);

        if ($duration > 0) {
            return __('call.panel.result.talk', ['duration' => $this->duration($duration)]);
        }

        return match ($attempt->status) {
            CallAttemptStatus::BUSY => __('call.panel.result.busy'),
            CallAttemptStatus::FAILED => __('call.panel.result.failed'),
            CallAttemptStatus::CANCELED => __('call.panel.result.canceled'),
            default => __('call.panel.result.no_answer'),
        };
    }

    /**
     * Warum ein Versuch nicht mitzaehlt. Unbekannte Gruende bleiben allgemein
     * -- ein interner Schluessel hat im Portal nichts zu suchen.
     */
    private function ignoreLabel(CallAttempt $attempt): string
    {
        $key = 'call.panel.ignored.'.(string) $attempt->ignore_reason;

        return __($key) === $key ? __('call.panel.ignored.other') : __($key);
    }

    /** Sekunden als "1:12". */
    private function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function openPollWindow(): void
    {
        $this->pollUntil = now()->addSeconds(self::POLL_WINDOW_SECONDS)->toIso8601String();
    }

    private function reload(): void
    {
        $this->loaded = null;
    }

    /**
     * Nur die eigenen Kaeufe. Ein fremder Beleg ist hier nicht "verboten",
     * sondern schlicht nicht vorhanden -- sonst liesse sich an der Antwort
     * ablesen, welche Kaufbelege es gibt.
     */
    public function purchase(): LeadPurchase
    {
        if ($this->loaded instanceof LeadPurchase) {
            return $this->loaded;
        }

        $purchase = LeadPurchase::query()
            ->with(['lead', 'callAttempts'])
            ->ofBuyer($this->tenant())
            ->whereKey($this->purchaseId)
            ->first();

        abort_unless($purchase instanceof LeadPurchase, 404);

        return $this->loaded = $purchase;
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
