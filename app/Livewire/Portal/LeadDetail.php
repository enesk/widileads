<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\BuyerLeadStatus;
use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Filament\Dashboard\Pages\PurchasedLeadDetail;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Models\CallAttempt;
use App\Models\LeadAnswer;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\CallerIdService;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\Twilio\OutboundCallFailed;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ein gekaufter Lead im eigenen Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\PurchasedLeadDetail
 * samt deren Anrufleiste (App\Livewire\Buyer\LeadCallPanel) -- beide Wege laufen
 * parallel, bis das Portal abgenommen ist.
 *
 * Wie dort haengt die Seite am KAUFBELEG und nicht am Lead: Der Beleg gehoert
 * dem Kaeufer, der Lead dem Betreiber. Deshalb wird ausdruecklich auf
 * `buyer_tenant_id` eingeschraenkt (Scope `ofBuyer`).
 *
 * **Diese Komponente maskiert nichts und entscheidet nichts.** Kontaktdaten
 * kommen ausschliesslich ueber den LeadPresenter; ob ein Anruf zulaessig ist,
 * prueft der CallService; ob ein Versuch zaehlt, der AttemptClassifier. Was
 * hier an Frist, Zaehler und Sperrzeit steht, ist die Vorschau derselben Regeln
 * aus config('lead_calls.*') -- sie erklaert dem Kaeufer, was passiert, und
 * bindet niemanden.
 *
 * Zwischen den Anfragen reist nur der Schluessel des Kaufbelegs. Ein Modell im
 * Livewire-Zustand muesste zwischen den Anfragen wiederhergestellt werden, und
 * zwar ohne den Kaeufer-Filter, der hier die einzige Zugangspruefung ist.
 */
#[Layout('components.layouts.portal-app')]
class LeadDetail extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;

    #[Locked]
    public string $purchaseId = '';

    /** Notizen des Kaeufers, mit Verzoegerung gespeichert. */
    public string $notes = '';

    /** Arbeitsstand des Kaeufers. Ohne Wirkung auf die Abrechnung. */
    public string $status = '';

    /** Rueckmeldung des letzten Anrufversuchs, als Band ueber der Seite. */
    public ?string $notice = null;

    /**
     * Bis zu diesem Zeitpunkt fragt die Ansicht waehrend eines Anrufs nach.
     * Dieselbe Begrenzung wie in der Filament-Fassung: Ein Anruf braucht
     * Sekunden, kein Dauerabo.
     */
    public ?string $pollUntil = null;

    private const POLL_WINDOW_SECONDS = 120;

    /** So lange gilt ein Versuch ohne Schlussmeldung als laufend. */
    private const STALE_ATTEMPT_SECONDS = 3600;

    private ?LeadPurchase $loaded = null;

    public function mount(string $purchase): void
    {
        $this->purchaseId = $purchase;

        $record = $this->purchase();

        $this->notes = (string) ($record->buyer_notes ?? '');
        $this->status = $record->buyer_status?->value ?? BuyerLeadStatus::OPEN->value;

        if ($this->runningAttempt() !== null) {
            $this->openPollWindow();
        }
    }

    public function render(): View
    {
        $purchase = $this->purchase();
        $lead = $purchase->lead;
        $presenter = $this->presenter();
        $deadline = $lead->deadline_at?->timezone(config('app.timezone'));
        $nextAllowedAt = $this->nextAllowedAt();
        $remaining = max(0, $this->requiredAttempts() - $this->failedCount());

        return view('livewire.portal.lead-detail', [
            'purchase' => $purchase,
            'lead' => $lead,
            'name' => $presenter->name(),
            'email' => $presenter->email(),
            'phone' => $presenter->phone(),
            'phoneMasked' => $presenter->isPhoneMasked(),
            'postalCode' => $presenter->postalCode(),
            'funnelName' => $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel'),
            'requestText' => $this->requestText(),
            'answers' => $this->answers(),
            'attempts' => $this->attempts(),
            'failed' => $this->failedCount(),
            'required' => $this->requiredAttempts(),
            'remaining' => $remaining,
            'deadline' => $deadline,
            'deadlineToday' => $deadline !== null && $deadline->isToday(),
            'deadlinePassed' => $deadline !== null && $deadline->isPast(),
            'nextAllowedAt' => $nextAllowedAt,
            'canCall' => $this->blockedReason() === null,
            'blockedReason' => $this->blockedReason(),
            'callingHint' => $this->callingHint(),
            'isOpen' => $lead->isOpen(),
            'price' => Money::format($purchase->price_cents, $purchase->currency),
            'priceStatus' => __('marketplace.purchased.detail.price_status.'.$purchase->status->value),
            'statusOptions' => BuyerLeadStatus::options(),
            'complaintUrl' => $this->complaintUrl(),
            'purchasedRelative' => $this->relativeTime($purchase->purchased_at),
            'backUrl' => route('portal.leads', ['tenant' => $this->portalTenant()->uuid]),
        ]);
    }

    /**
     * Speichert die Notizen.
     *
     * Ausgeloest vom verzoegerten `wire:model.live.debounce` der Ansicht: Ohne
     * die Verzoegerung ginge je Tastendruck eine Anfrage zum Server.
     */
    public function updatedNotes(): void
    {
        $this->purchase()->update(['buyer_notes' => $this->notes === '' ? null : $this->notes]);

        $this->toast(__('portal.toast.note_saved'));
    }

    public function updatedStatus(string $value): void
    {
        $status = BuyerLeadStatus::tryFrom($value);

        $this->purchase()->update(['buyer_status' => $status]);

        $this->status = $status?->value ?? BuyerLeadStatus::OPEN->value;

        $this->toast(__('portal.toast.status_saved'));
    }

    /**
     * Startet den Anruf (FB-081).
     *
     * Der Name darf NICHT `call` lauten: `$wire.call(methode)` ist die
     * eingebaute Livewire-Schnittstelle im Browser, `wire:click="call"` schickt
     * dem Server ein leeres Kommando.
     */
    public function startCall(CallService $calls): void
    {
        $user = $this->portalUser();

        if (! $user instanceof User) {
            return;
        }

        $this->notice = null;

        try {
            $calls->start($this->purchase(), $user, $this->portalTenant());
        } catch (CallNotPossible $exception) {
            $this->notice = $exception->translated();
            $this->loaded = null;

            return;
        } catch (OutboundCallFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio konnte den Anruf nicht starten.', [
                'message' => $exception->getMessage(),
            ]);

            $this->notice = __('call.attempt.errors.provider_failed');

            return;
        }

        $this->loaded = null;
        $this->openPollWindow();
    }

    public function refreshStatus(): void
    {
        $this->loaded = null;

        if ($this->runningAttempt() === null) {
            $this->pollUntil = null;
        }
    }

    public function shouldPoll(): bool
    {
        return $this->pollUntil !== null && Carbon::parse($this->pollUntil)->isFuture();
    }

    public function presenter(): LeadPresenter
    {
        return new LeadPresenter($this->purchase()->lead, $this->portalUser());
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
            ->with([
                'complaint',
                'callAttempts',
                'lead.answers',
                // Ohne Mandanten-Scope: Fragebogen und Fassung gehoeren dem
                // Betreiber, nicht dem Kaeufer.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                'lead.funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->ofBuyer($this->portalTenant())
            ->whereKey($this->purchaseId)
            ->first();

        abort_unless($purchase instanceof LeadPurchase, 404);

        return $this->loaded = $purchase;
    }

    /**
     * Der Freitext der Anfrage -- die laengste Antwort aus dem Fragebogen.
     *
     * Ein eigenes Feld dafuer gibt es nicht: Welches Feld die Schilderung
     * traegt, entscheidet der Betreiber beim Bauen des Fragebogens.
     */
    private function requestText(): ?string
    {
        $longest = null;

        foreach ($this->purchase()->lead->answers as $answer) {
            if ($answer->isPersonal() || ! is_string($answer->value)) {
                continue;
            }

            if (mb_strlen($answer->value) < 80) {
                continue;
            }

            if ($longest === null || mb_strlen($answer->value) > mb_strlen($longest)) {
                $longest = $answer->value;
            }
        }

        return $longest;
    }

    /**
     * Die Antworten aus dem Fragebogen, mit den Beschriftungen der Fassung,
     * unter der der Lead entstanden ist.
     *
     * Ohne die reservierten Kontaktfelder: Sie stehen schon oben, und zwar aus
     * dem Presenter. Ohne den Freitext: Der steht als Zitat daneben.
     *
     * @return array<string, string>
     */
    private function answers(): array
    {
        $lead = $this->purchase()->lead;
        $snapshot = $lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);
        $requestText = $this->requestText();

        return $lead->answers
            ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal()
                || ($requestText !== null && $answer->value === $requestText))
            ->mapWithKeys(static function (LeadAnswer $answer) use ($labels, $options): array {
                $readable = static fn (mixed $single): string => $options[$answer->field_key][(string) $single]
                    ?? (string) $single;

                $value = $answer->value;

                return [
                    $labels[$answer->field_key] ?? $answer->field_key => match (true) {
                        is_array($value) => implode(', ', array_map($readable, $value)),
                        is_bool($value) => $value ? __('leads.detail.yes') : __('leads.detail.no'),
                        is_scalar($value) => $readable($value),
                        default => '-',
                    },
                ];
            })
            ->all();
    }

    /**
     * Der Verlauf, neueste zuerst.
     *
     * @return list<array{when: string, result: string, icon: string, counted: bool, note: string|null}>
     */
    private function attempts(): array
    {
        return $this->purchase()->callAttempts->map(function (CallAttempt $attempt): array {
            $counted = $attempt->outcome?->counts() ?? true;
            $talked = (int) ($attempt->duration_seconds ?? 0) > 0
                && ! str_starts_with((string) $attempt->answered_by, 'machine');

            return [
                'when' => $this->attemptWhen($attempt),
                'result' => $this->resultLabel($attempt),
                'icon' => match (true) {
                    $attempt->ended_at === null => 'phone',
                    str_starts_with((string) $attempt->answered_by, 'machine') => 'voicemail',
                    $talked => 'phone',
                    default => 'close',
                },
                'counted' => $counted,
                'note' => $counted ? null : $this->ignoreLabel($attempt),
            ];
        })->values()->all();
    }

    /**
     * Zeitpunkt und Dauer eines Versuchs, so wie im Entwurf: "heute, 09:12 ·
     * 0:18 min".
     */
    private function attemptWhen(CallAttempt $attempt): string
    {
        $moment = $attempt->started_at?->timezone(config('app.timezone'));

        if ($moment === null) {
            return '-';
        }

        $when = match (true) {
            $moment->isToday() => __('marketplace.purchased.portal.calls.when_today', ['time' => $moment->format('H:i')]),
            $moment->isYesterday() => __('marketplace.purchased.portal.calls.when_yesterday', ['time' => $moment->format('H:i')]),
            default => __('marketplace.purchased.portal.calls.when_on', [
                'date' => $moment->format('d.m.Y'),
                'time' => $moment->format('H:i'),
            ]),
        };

        $seconds = (int) ($attempt->duration_seconds ?? 0);

        return $when.' · '.__('marketplace.purchased.portal.calls.duration', [
            'duration' => sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60),
        ]);
    }

    /** Gueltige Fehlversuche -- die Zahl, die das Regelwerk zaehlt. */
    private function failedCount(): int
    {
        return $this->purchase()->callAttempts
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->count();
    }

    private function requiredAttempts(): int
    {
        return (int) config('lead_calls.unreachable_attempts');
    }

    private function runningAttempt(): ?CallAttempt
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
    private function callingHint(): ?string
    {
        $attempt = $this->runningAttempt();

        return $attempt === null ? null : __('call.panel.calling', ['number' => $attempt->caller_number]);
    }

    /**
     * Warum gerade nicht angerufen werden kann -- oder null. Dieselben Gruende
     * in derselben Reihenfolge wie im CallService.
     */
    private function blockedReason(): ?string
    {
        $lead = $this->purchase()->lead;

        if (! $lead->isOpen()) {
            return __('call.attempt.errors.lead_resolved');
        }

        if ($this->runningAttempt() !== null) {
            return __('call.attempt.errors.already_running');
        }

        $user = $this->portalUser();
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
    private function nextAllowedAt(): ?Carbon
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
     * Das Ergebnis eines Versuchs im Klartext. Bewertet wird hier nichts --
     * gelesen wird, was der AttemptClassifier bereits entschieden hat.
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
            return __('call.panel.result.talk', [
                'duration' => sprintf('%d:%02d', intdiv($duration, 60), $duration % 60),
            ]);
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

    /**
     * Die Reklamation liegt weiterhin im Dashboard-Panel: Das Formular ist der
     * naechste Baustein des Portals, bis dahin fuehrt der Verweis an die Stelle,
     * an der es sie schon gibt.
     */
    private function complaintUrl(): string
    {
        return PurchasedLeadDetail::getUrl(
            ['purchase' => $this->purchase()->getKey()],
            panel: 'dashboard',
            tenant: $this->portalTenant(),
        );
    }

    /** "gekauft gestern, 11:05" -- dieselben Bausteine wie in "Meine Leads". */
    private function relativeTime(?Carbon $moment): string
    {
        if ($moment === null) {
            return '-';
        }

        return match (true) {
            $moment->isToday() => (string) __('marketplace.purchased.bought_today', ['time' => $moment->format('H:i')]),
            $moment->isYesterday() => (string) __('marketplace.purchased.bought_yesterday', ['time' => $moment->format('H:i')]),
            default => (string) __('marketplace.purchased.bought_on', ['date' => $moment->format('d.m.Y')]),
        };
    }

    private function openPollWindow(): void
    {
        $this->pollUntil = now()->addSeconds(self::POLL_WINDOW_SECONDS)->toIso8601String();
    }
}
