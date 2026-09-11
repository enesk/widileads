<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\AuditAction;
use App\Constants\BuyerLeadStatus;
use App\Constants\CallAttemptOutcome;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadContactStatus;
use App\Funnel\Snapshots\SnapshotLabels;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Livewire\Portal\Concerns\ShowsToasts;
use App\Models\CallAttempt;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\AuditLogger;
use App\Services\CallerIdService;
use App\Services\CallNotPossible;
use App\Services\CallService;
use App\Services\Twilio\OutboundCallFailed;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Meine Leads" im eigenen Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\PurchasedLeads
 * nach dem Entwurf `meine-leads.html`. Beide Wege laufen parallel, bis das
 * Portal abgenommen ist.
 *
 * **Die Seite ist nach dem Stand der Erreichbarkeit geordnet, nicht nach dem
 * Kaufdatum.** Das ist die Entscheidung des Entwurfs und keine Gestaltung: Was
 * ein Kaeufer hier braucht, ist die Antwort auf "wo muss ich heute noch
 * anrufen" -- alles andere ist Archiv. Deshalb steht die Frist ganz oben, und
 * der Reiter "Offen" traegt die Arbeit.
 *
 * **Diese Komponente maskiert nichts und entscheidet nichts.** Kontaktdaten
 * kommen ausschliesslich ueber den LeadPresenter (FB-032); ob ein Anruf
 * zulaessig ist, entscheidet allein der CallService; ob ein Versuch zaehlt, der
 * AttemptClassifier. Was hier steht -- Zaehler, Sperrzeit, Frist -- ist die
 * Vorschau derselben Regeln aus config('lead_calls.*').
 *
 * Gezeigt werden ausschliesslich die Kaeufe des aktiven Mandanten: Der
 * Kaufbeleg gehoert dem Kaeufer, der Lead dem Betreiber. Eingeschraenkt wird
 * deshalb ausdruecklich ueber `ofBuyer`, nicht ueber einen Mandanten-Scope, der
 * auf die falsche Spalte zielte.
 */
#[Layout('components.layouts.portal-app')]
class PurchasedLeads extends Component
{
    use InteractsWithPortalTenant;
    use ShowsToasts;
    use WithPagination;

    public const STATUS_ALL = 'all';

    public const STATUS_OPEN = 'open';

    public const STATUS_REACHED = 'reached';

    public const STATUS_UNREACHED = 'unreached';

    public const SORT_DEADLINE = 'deadline';

    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public const GROUP_TODAY = 'today';

    public const GROUP_WEEK = 'week';

    public const GROUP_DONE = 'done';

    /** Zeilen je Seite. */
    public const PER_PAGE = 20;

    /** Mehr Merkmale passen nicht in eine Zeile, ohne sie zu sprengen. */
    private const MAX_CHIPS = 4;

    #[Url(as: 'status', except: self::STATUS_ALL)]
    public string $status = self::STATUS_ALL;

    #[Url(as: 'sortierung', except: self::SORT_DEADLINE)]
    public string $sort = self::SORT_DEADLINE;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    /**
     * Laeuft nach allen boot-Haken, der Mandant steht also bereits.
     */
    public function booted(): void
    {
        $user = $this->portalUser();

        abort_unless(
            $user instanceof User && Gate::forUser($user)->allows('marketplace.access', $this->portalTenant()),
            403,
        );
    }

    /**
     * Ein geaenderter Reiter oder Filter beginnt wieder auf Seite eins -- sonst
     * landet der Kaeufer auf einer Seite 3, die es nach dem Filtern nicht mehr
     * gibt.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'sort', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, $this->statusTabs()) ? $status : self::STATUS_ALL;
        $this->resetPage();
    }

    /**
     * Startet den Anruf zu einem gekauften Lead (FB-081).
     *
     * Die Komponente prueft nichts selbst: Sperrzeit, eigene Rufnummer,
     * entschiedene Erreichbarkeit und laufender Versuch entscheidet der
     * CallService. Was hier abgefangen wird, sind seine Absagen -- das sind
     * Hinweise an den Kaeufer, keine Fehler.
     *
     * Nicht `call` nennen: Der Name ist in Livewire belegt, `wire:click="call"`
     * schickt dem Server ein leeres Kommando.
     */
    public function startCall(int $purchaseId): void
    {
        $user = $this->portalUser();
        $purchase = $this->findPurchase($purchaseId);

        if (! $user instanceof User || ! $purchase instanceof LeadPurchase) {
            $this->warn(__('call.attempt.errors.foreign_purchase'));

            return;
        }

        try {
            app(CallService::class)->start($purchase, $user, $this->portalTenant());
        } catch (CallNotPossible $exception) {
            $this->warn($exception->translated());

            return;
        } catch (OutboundCallFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio konnte den Anruf nicht starten.', [
                'message' => $exception->getMessage(),
            ]);

            $this->warn(__('call.attempt.errors.provider_failed'));

            return;
        }

        $this->toast(__('call.attempt.started_body'), __('call.attempt.started'));
    }

    public function render(): View
    {
        $purchases = $this->paginator();

        $rows = $this->rows($purchases);

        return view('livewire.portal.purchased-leads', [
            'purchases' => $purchases,
            'rows' => $rows,
            'groups' => $this->groups($rows),
            'tabs' => $this->tabs(),
            'sortOptions' => $this->sortOptions(),
            'deadlineNotice' => $this->deadlineNotice(),
            'emptyText' => $this->emptyText(),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->portalTenant()->uuid]),
        ])->title(__('marketplace.purchased.heading'));
    }

    /**
     * Gibt die eigenen Kaeufe als CSV aus -- derselbe Export wie im Dashboard.
     *
     * Der Export enthaelt Kontaktdaten und wird deshalb im Audit-Log
     * festgehalten (FB-005): Wer Daten aus dem System traegt, hinterlaesst eine
     * Spur.
     */
    public function exportCsv(): StreamedResponse
    {
        $tenant = $this->portalTenant();
        $viewer = $this->portalUser();
        $purchases = $this->baseQuery()->get();

        app(AuditLogger::class)->log(
            AuditAction::DATA_EXPORTED,
            null,
            ['subject' => 'purchased_leads', 'count' => $purchases->count()],
            $tenant,
        );

        $rows = $purchases->map(function (LeadPurchase $purchase) use ($viewer): array {
            $presenter = new LeadPresenter($purchase->lead, $viewer);

            return [
                $purchase->purchased_at?->toDateTimeString(),
                $purchase->lead->funnel?->name,
                $presenter->name(),
                $presenter->email(),
                $presenter->phone(),
                $presenter->postalCode(),
                (string) $purchase->lead->score,
                $purchase->buyer_feedback?->value,
            ];
        })->all();

        $headers = [
            __('marketplace.purchased.csv.purchased_at'),
            __('marketplace.purchased.csv.funnel'),
            __('marketplace.purchased.csv.name'),
            __('marketplace.purchased.csv.email'),
            __('marketplace.purchased.csv.phone'),
            __('marketplace.purchased.csv.postal_code'),
            __('marketplace.purchased.csv.score'),
            __('marketplace.purchased.csv.feedback'),
        ];

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Byte Order Mark, damit Excel die Umlaute richtig liest.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 'meine-leads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Die Reiter samt Zaehler. Gezaehlt wird ueber die Suche, aber nicht ueber
     * den eigenen Reiter -- sonst stuende neben "Offen" immer die Zahl der
     * gerade sichtbaren Karten.
     *
     * @return list<array{key: string, label: string, count: int, active: bool}>
     */
    private function tabs(): array
    {
        return array_map(fn (string $key, string $label): array => [
            'key' => $key,
            'label' => $label,
            'count' => $this->withStatus($this->searchedQuery(), $key)->count(),
            'active' => $this->status === $key,
        ], array_keys($this->statusTabs()), array_values($this->statusTabs()));
    }

    /**
     * @return array<string, string>
     */
    private function statusTabs(): array
    {
        return [
            self::STATUS_ALL => __('marketplace.purchased.tabs.all'),
            self::STATUS_OPEN => __('marketplace.purchased.tabs.open'),
            self::STATUS_REACHED => __('marketplace.purchased.tabs.reached'),
            self::STATUS_UNREACHED => __('marketplace.purchased.tabs.unreached'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            self::SORT_DEADLINE => __('marketplace.purchased.sort.deadline'),
            self::SORT_NEWEST => __('marketplace.purchased.sort.newest'),
            self::SORT_OLDEST => __('marketplace.purchased.sort.oldest'),
        ];
    }

    /**
     * Jeder Reiter hat seinen eigenen Leertext. "Nichts gefunden" ueber einer
     * leeren Liste sagt dem Kaeufer nicht, ob er nichts zu tun hat oder etwas
     * fehlt.
     *
     * @return array{title: string, text: string}
     */
    private function emptyText(): array
    {
        $key = $this->search !== '' ? 'search' : $this->status;

        return [
            'title' => (string) __('marketplace.purchased.empty_state.'.$key.'.title'),
            'text' => (string) __('marketplace.purchased.empty_state.'.$key.'.text'),
        ];
    }

    /**
     * Der Hinweis ueber dem Raster: heute endende Fristen.
     *
     * Das Wichtigste der ganzen Seite -- eine verstrichene Frist kostet den
     * Kaeufer Geld, weil der Lead dann berechnet wird, ohne dass er jemanden
     * gesprochen hat.
     *
     * @return array{count: int, name: string, id: int}|null
     */
    private function deadlineNotice(): ?array
    {
        $dueToday = $this->baseQuery()
            ->whereHas('lead', static fn (Builder $lead): Builder => $lead
                ->where('contact_status', LeadContactStatus::OPEN)
                ->whereBetween('deadline_at', [now()->startOfDay(), now()->endOfDay()]))
            ->get();

        $first = $dueToday->first();

        if (! $first instanceof LeadPurchase) {
            return null;
        }

        return [
            'count' => $dueToday->count(),
            'name' => (new LeadPresenter($first->lead, $this->portalUser()))->name(),
            'id' => (int) $first->getKey(),
        ];
    }

    /**
     * Die Zeilen dieser Seite, fertig fuer die Ansicht aufbereitet.
     *
     * Die Ansicht bekommt ausschliesslich fertige Werte und ruft keinen Dienst
     * auf -- so laesst sich das Markup des Entwurfs eins zu eins eintauschen.
     *
     * @param  LengthAwarePaginator<int, LeadPurchase>  $purchases
     * @return list<array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $purchases): array
    {
        $callerIdReady = $this->callerIdReady();

        return collect($purchases->items())
            ->map(function (LeadPurchase $purchase) use ($callerIdReady): array {
                $presenter = new LeadPresenter($purchase->lead, $this->portalUser());
                $lead = $purchase->lead;
                $running = $this->runningAttempt($purchase);

                return [
                    'id' => (int) $purchase->getKey(),
                    'url' => route('portal.leads.show', [
                        'tenant' => $this->portalTenant()->uuid,
                        'purchase' => $purchase->getKey(),
                    ]),
                    'name' => $presenter->name(),
                    'purchased_at' => $this->relativeTime($purchase->purchased_at),
                    'purchased_at_exact' => $purchase->purchased_at?->format('d.m.Y H:i') ?? '-',
                    'funnel' => $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel'),
                    'postal_code' => $presenter->postalCode(),
                    // Kontaktdaten ausschliesslich aus dem Presenter. Ob die
                    // Rufnummer verkuerzt ist, hat der LeadContactResolver
                    // entschieden, nicht diese Seite (FB-085).
                    'phone' => $presenter->phone(),
                    'phone_masked' => $presenter->isPhoneMasked(),
                    'phone_hint' => $presenter->phoneHint(),
                    'phone_link' => $presenter->isPhoneMasked() ? null : 'tel:'.preg_replace('/[^+0-9]/', '', $presenter->phone()),
                    'email' => $presenter->email(),
                    'badge' => $this->badge($purchase),
                    'status_line' => $this->statusLine($purchase),
                    // In welchen Abschnitt die Zeile gehoert. Die Gruppe
                    // beantwortet die Frage, mit der ein Kaeufer diese Seite
                    // oeffnet: was kostet mich heute Geld?
                    'group' => $this->groupOf($purchase),
                    'initials' => $this->initials($presenter->name()),
                    'avatar_tone' => $this->avatarTone($purchase),
                    // Versuchsfortschritt als Balken. Erledigte Leads zeigen
                    // stattdessen, was abgerechnet wurde.
                    'attempts_done' => $this->failedAttempts($purchase),
                    'attempts_total' => (int) config('lead_calls.unreachable_attempts'),
                    'settlement' => $this->settlementLabel($purchase),
                    // Die eigene Notiz des Kaeufers, nicht sein abschliessendes
                    // Urteil: Was hier steht, hat er sich beim letzten Anruf
                    // selbst notiert.
                    'note' => $purchase->buyer_notes === null || trim($purchase->buyer_notes) === ''
                        ? null
                        : trim($purchase->buyer_notes),
                    'attributes' => $this->qualificationAnswers($purchase),
                    // Nur die Werte, nicht die Fragen: In einer Zeile ist
                    // "Maine Coon" lesbar, "Rasse: Maine Coon" nicht mehr.
                    'chips' => $this->chips($purchase),
                    'open' => $lead->isOpen(),
                    'billable' => $lead->contact_status === LeadContactStatus::BILLABLE,
                    // Nur eine Vorschau fuer die Oberflaeche: verbindlich
                    // entscheidet der CallService im Moment des Anrufs.
                    'can_call' => $lead->isOpen() && $running === null && $callerIdReady,
                    'call_hint' => $this->callHint($purchase, $callerIdReady),
                    'calling' => $running !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Die drei Abschnitte des Entwurfs, in dieser Reihenfolge und nur, wenn sie
     * Zeilen haben.
     *
     * Die Gruppen entstehen aus den Zeilen der aktuellen Seite und nicht aus
     * einer eigenen Abfrage: Sonst stuende ueber einem Abschnitt eine Zahl, zu
     * der die Liste darunter nicht passt.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, title: string, hint: string, urgent: bool, rows: list<array<string, mixed>>}>
     */
    private function groups(array $rows): array
    {
        $definitions = [
            self::GROUP_TODAY => ['title' => 'today', 'urgent' => true],
            self::GROUP_WEEK => ['title' => 'week', 'urgent' => false],
            self::GROUP_DONE => ['title' => 'done', 'urgent' => false],
        ];

        $groups = [];

        foreach ($definitions as $key => $definition) {
            $inGroup = array_values(array_filter($rows, static fn (array $row): bool => $row['group'] === $key));

            if ($inGroup === []) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'title' => (string) __('marketplace.purchased.groups.'.$definition['title'].'.title'),
                'hint' => (string) trans_choice(
                    'marketplace.purchased.groups.'.$definition['title'].'.hint',
                    count($inGroup),
                    ['count' => count($inGroup)],
                ),
                // Nur der erste Abschnitt traegt den Amber-Rand. Wenn drei
                // Kaesten dringend aussehen, ist keiner mehr dringend.
                'urgent' => $definition['urgent'],
                'rows' => $inGroup,
            ];
        }

        return $groups;
    }

    private function groupOf(LeadPurchase $purchase): string
    {
        if (! $purchase->lead->isOpen()) {
            return self::GROUP_DONE;
        }

        return $this->isDueToday($purchase) ? self::GROUP_TODAY : self::GROUP_WEEK;
    }

    /**
     * Zwei Buchstaben fuer den Kreis am Zeilenanfang.
     *
     * Faellt auf den ersten Buchstaben zurueck, wenn nur ein Wort dasteht --
     * und auf ein Fragezeichen, wenn der Presenter gar keinen Namen liefert.
     */
    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        $first = mb_substr((string) $parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) $parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Die Farbe des Kreises sagt dasselbe wie die Pille -- nur schneller.
     */
    private function avatarTone(LeadPurchase $purchase): string
    {
        return match ($purchase->lead->contact_status) {
            LeadContactStatus::BILLABLE => 'emerald',
            LeadContactStatus::UNREACHABLE => 'neutral',
            default => 'brand',
        };
    }

    /**
     * Was mit dem Geld passiert ist -- oder null, solange die Frist laeuft.
     */
    private function settlementLabel(LeadPurchase $purchase): ?string
    {
        return match ($purchase->lead->contact_status) {
            LeadContactStatus::BILLABLE => (string) __('marketplace.purchased.settlement.billed', [
                'amount' => Money::format((int) $purchase->price_cents),
            ]),
            LeadContactStatus::UNREACHABLE => (string) __('marketplace.purchased.settlement.released'),
            default => null,
        };
    }

    /**
     * Die Merkmale als kurze Chips: nur die Antworten, ohne die Fragen.
     *
     * In einer Zeile ist Platz fuer vier Woerter, nicht fuer vier Saetze. Wer
     * wissen will, welche Frage dahintersteht, oeffnet die Detailseite -- dort
     * stehen Frage und Antwort vollstaendig.
     *
     * @return list<string>
     */
    private function chips(LeadPurchase $purchase): array
    {
        $chips = [];

        foreach ($this->qualificationAnswers($purchase) as $answer) {
            $value = trim($answer['value']);

            if ($value === '') {
                continue;
            }

            $chips[] = $value;

            if (count($chips) === self::MAX_CHIPS) {
                break;
            }
        }

        return $chips;
    }

    /**
     * Die Pille neben dem Namen: der Stand in zwei Worten.
     *
     * In einer Zeile ist die Pille die einzige Stelle, an der die Frist steht
     * -- deshalb nennt sie bei offenen Leads die Uhrzeit oder die Tage und
     * nicht nur "Frist laeuft".
     *
     * @return array{label: string, tone: string, icon: string}
     */
    private function badge(LeadPurchase $purchase): array
    {
        $lead = $purchase->lead;

        if ($lead->contact_status === LeadContactStatus::BILLABLE) {
            // Der Arbeitsstand des Kaeufers schlaegt "erreicht": Ein
            // vereinbarter Termin ist die bessere Nachricht, und er hat den
            // Lead genau dafuer gekauft. An der Abrechnung aendert das nichts.
            $status = $purchase->buyer_status;

            if ($status instanceof BuyerLeadStatus && $status !== BuyerLeadStatus::OPEN) {
                return [
                    'label' => $status->label(),
                    'tone' => 'emerald',
                    'icon' => $status === BuyerLeadStatus::APPOINTMENT ? 'calendar' : 'check',
                ];
            }

            return [
                'label' => (string) __('marketplace.purchased.badge.reached_on', [
                    'date' => $this->resolvedOn($purchase),
                ]),
                'tone' => 'emerald',
                'icon' => 'check',
            ];
        }

        if ($lead->contact_status === LeadContactStatus::UNREACHABLE) {
            return [
                'label' => (string) __('marketplace.purchased.badge.unreached_on', [
                    'date' => $this->resolvedOn($purchase),
                ]),
                'tone' => 'neutral',
                'icon' => 'phone-off',
            ];
        }

        $deadline = $lead->deadline_at;

        if ($deadline === null) {
            return [
                'label' => (string) __('marketplace.purchased.line.deadline_none'),
                'tone' => 'brand',
                'icon' => 'clock',
            ];
        }

        if ($deadline->isToday()) {
            return [
                'label' => (string) __('marketplace.purchased.badge.today_at', ['time' => $deadline->format('H:i')]),
                'tone' => 'amber',
                'icon' => 'clock',
            ];
        }

        if ($deadline->isPast()) {
            return [
                'label' => (string) __('marketplace.purchased.line.deadline_over'),
                'tone' => 'amber',
                'icon' => 'clock',
            ];
        }

        return [
            'label' => (string) trans_choice(
                'marketplace.purchased.badge.days_left',
                max(1, (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay())),
                ['days' => max(1, (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay()))],
            ),
            'tone' => 'brand',
            'icon' => 'clock',
        ];
    }

    /**
     * Das Datum, an dem die Erreichbarkeit entschieden wurde.
     */
    private function resolvedOn(LeadPurchase $purchase): string
    {
        $moment = $purchase->lead->resolved_at
            ?? $this->answeredAttempt($purchase)?->started_at
            ?? $purchase->lead->deadline_at;

        return $moment?->format('d.m.') ?? '-';
    }

    /**
     * Die Statuszeile: in einem Satz, was der Kaeufer wissen muss.
     *
     * Drei Faelle, drei Saetze -- offen nennt Versuche, Sperrzeit und Frist,
     * erreicht nennt Gespraech und abgerechneten Betrag, nicht erreicht nennt
     * die Freigabe.
     */
    private function statusLine(LeadPurchase $purchase): string
    {
        $lead = $purchase->lead;
        $failed = $this->failedAttempts($purchase);
        $required = (int) config('lead_calls.unreachable_attempts');

        if ($lead->contact_status === LeadContactStatus::BILLABLE) {
            $talk = $this->answeredAttempt($purchase);

            if ($talk instanceof CallAttempt) {
                return __('marketplace.purchased.line.billable', [
                    'duration' => $this->duration((int) ($talk->duration_seconds ?? 0)),
                    'date' => $talk->started_at?->format('d.m.') ?? '-',
                    'amount' => Money::format((int) $purchase->price_cents),
                ]);
            }

            return __('marketplace.purchased.line.billable_no_talk', [
                'amount' => Money::format((int) $purchase->price_cents),
            ]);
        }

        if ($lead->contact_status === LeadContactStatus::UNREACHABLE) {
            return __('marketplace.purchased.line.unreachable', [
                'count' => $failed,
                'required' => $required,
                'amount' => Money::format((int) $purchase->price_cents),
            ]);
        }

        $next = $this->nextAllowedAt($purchase);

        return __('marketplace.purchased.line.open', [
            'count' => $failed,
            'required' => $required,
            'next' => $next === null
                ? __('marketplace.purchased.line.next_now')
                : __('marketplace.purchased.line.next_at', ['time' => $next->format('H:i')]),
            'deadline' => $this->deadlineText($purchase),
        ]);
    }

    /**
     * "Frist endet heute, 23:59" oder "Frist endet in 6 Tagen".
     */
    private function deadlineText(LeadPurchase $purchase): string
    {
        $deadline = $purchase->lead->deadline_at;

        if ($deadline === null) {
            return (string) __('marketplace.purchased.line.deadline_none');
        }

        if ($deadline->isPast()) {
            return (string) __('marketplace.purchased.line.deadline_over');
        }

        if ($deadline->isToday()) {
            return (string) __('marketplace.purchased.line.deadline_today', ['time' => $deadline->format('H:i')]);
        }

        return (string) __('marketplace.purchased.line.deadline_days', [
            'days' => max(1, (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay())),
        ]);
    }

    /**
     * Warum der Anrufknopf gerade nicht geht -- oder null.
     *
     * Ein ausgegrauter Knopf ohne Begruendung ist eine Sackgasse. Dieselben
     * Gruende, die der CallService kennt, in derselben Reihenfolge.
     */
    private function callHint(LeadPurchase $purchase, bool $callerIdReady): ?string
    {
        if (! $purchase->lead->isOpen()) {
            return null;
        }

        if ($this->runningAttempt($purchase) !== null) {
            return (string) __('call.attempt.errors.already_running');
        }

        if (! $callerIdReady) {
            return (string) __('call.attempt.errors.caller_id_missing');
        }

        $next = $this->nextAllowedAt($purchase);

        return $next === null
            ? null
            : (string) __('call.attempt.errors.too_soon', ['time' => $next->format('H:i')]);
    }

    /**
     * Hat der angemeldete Mitarbeiter eine bestaetigte eigene Rufnummer?
     *
     * Einmal je Anfrage, nicht je Karte: Die Bestaetigung haengt am Benutzer,
     * nicht am Lead.
     */
    private function callerIdReady(): bool
    {
        $user = $this->portalUser();

        if (! $user instanceof User) {
            return false;
        }

        $callerId = app(CallerIdService::class)->forUser($user);

        return $callerId !== null && $callerId->isUsableAsCallerId();
    }

    /**
     * Der Versuch, der gerade laeuft -- oder null. Ohne Schlussmeldung gilt ein
     * Versuch nur so lange als laufend, wie er frisch ist.
     */
    private function runningAttempt(LeadPurchase $purchase): ?CallAttempt
    {
        return $purchase->callAttempts
            ->first(static fn (CallAttempt $attempt): bool => $attempt->ended_at === null
                && $attempt->started_at instanceof Carbon
                && $attempt->started_at->gt(now()->subHour()));
    }

    private function answeredAttempt(LeadPurchase $purchase): ?CallAttempt
    {
        return $purchase->callAttempts
            ->where('outcome', CallAttemptOutcome::ANSWERED)
            ->sortByDesc('started_at')
            ->first();
    }

    /** Gueltige Fehlversuche -- die Zahl, die das Regelwerk zaehlt (FB-083). */
    private function failedAttempts(LeadPurchase $purchase): int
    {
        return $purchase->callAttempts
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->count();
    }

    /**
     * Ab wann der naechste Versuch gilt -- oder null, wenn der Mindestabstand
     * eingehalten ist.
     */
    private function nextAllowedAt(LeadPurchase $purchase): ?Carbon
    {
        $hours = (int) config('lead_calls.retry_min_hours');

        if ($hours <= 0) {
            return null;
        }

        /** @var CallAttempt|null $last */
        $last = $purchase->callAttempts
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->sortByDesc('started_at')
            ->first();

        if ($last === null || ! $last->started_at instanceof Carbon) {
            return null;
        }

        $next = $last->started_at->copy()->addHours($hours);

        return $next->isFuture() ? $next : null;
    }

    private function isDueToday(LeadPurchase $purchase): bool
    {
        $deadline = $purchase->lead->deadline_at;

        return $deadline !== null && $deadline->isToday();
    }

    /**
     * @return LengthAwarePaginator<int, LeadPurchase>
     */
    private function paginator(): LengthAwarePaginator
    {
        $query = $this->withStatus($this->searchedQuery(), $this->status);

        return $this->sorted($query)->paginate(self::PER_PAGE);
    }

    /**
     * @param  Builder<LeadPurchase>  $query
     * @return Builder<LeadPurchase>
     */
    private function sorted(Builder $query): Builder
    {
        if ($this->sort === self::SORT_OLDEST) {
            return $query->orderBy('purchased_at');
        }

        if ($this->sort === self::SORT_NEWEST) {
            return $query->orderByDesc('purchased_at');
        }

        // Frist zuerst. Der Lead traegt die Frist, nicht der Kaufbeleg -- und
        // Kaeufe ohne Frist gehoeren nach hinten, nicht nach vorn.
        return $query
            ->orderByRaw('(select deadline_at is null from leads where leads.id = lead_purchases.lead_id) asc')
            ->orderByRaw('(select deadline_at from leads where leads.id = lead_purchases.lead_id) asc')
            ->orderByDesc('purchased_at');
    }

    /**
     * @param  Builder<LeadPurchase>  $query
     * @return Builder<LeadPurchase>
     */
    private function withStatus(Builder $query, string $status): Builder
    {
        $contactStatus = match ($status) {
            self::STATUS_OPEN => LeadContactStatus::OPEN,
            self::STATUS_REACHED => LeadContactStatus::BILLABLE,
            self::STATUS_UNREACHED => LeadContactStatus::UNREACHABLE,
            default => null,
        };

        if ($contactStatus === null) {
            return $query;
        }

        return $query->whereHas(
            'lead',
            static fn (Builder $lead): Builder => $lead->where('contact_status', $contactStatus),
        );
    }

    /**
     * Die Suche ueber Name und Ort.
     *
     * Der Name steht in den Antworten, die Postleitzahl als Spalte am Lead.
     * Gesucht wird auf den echten Werten -- eine Suche auf der Anzeigefassung
     * fand bei verkuerzten Daten nichts.
     *
     * @return Builder<LeadPurchase>
     */
    private function searchedQuery(): Builder
    {
        $query = $this->baseQuery();
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        return $query->whereHas('lead', static function (Builder $lead) use ($term): Builder {
            return $lead->where(static function (Builder $inner) use ($term): void {
                $inner
                    ->where('postal_code', 'like', $term.'%')
                    ->orWhereHas('answers', static fn (Builder $answers): Builder => $answers
                        ->whereIn('field_key', [
                            FunnelFieldKey::VORNAME->value,
                            FunnelFieldKey::NACHNAME->value,
                            FunnelFieldKey::NAME->value,
                        ])
                        ->where('value', 'like', '%'.$term.'%'));
            });
        });
    }

    /**
     * Laedt einen Kaufbeleg -- immer ueber `ofBuyer`, damit ein fremder
     * Schluessel schlicht nichts findet. Das ist die einzige Zugangspruefung
     * und darf nicht durch eine Suche am Modell ersetzt werden.
     */
    private function findPurchase(int $purchaseId): ?LeadPurchase
    {
        return $this->baseQuery()->whereKey($purchaseId)->first();
    }

    /**
     * @return Builder<LeadPurchase>
     */
    private function baseQuery(): Builder
    {
        return LeadPurchase::query()
            ->with([
                'callAttempts',
                'complaint',
                'lead.answers',
                // Ohne Mandanten-Scope: Fragebogen und Fassung gehoeren dem
                // Betreiber, nicht dem Kaeufer. Mit Scope kaeme hier immer null
                // heraus, und der Kaeufer saehe seine Kaeufe ohne Herkunft.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
                'lead.funnelVersion' => static fn (Relation $version) => $version->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->ofBuyer($this->portalTenant());
    }

    /**
     * Die Qualifizierungsantworten -- **ohne** die reservierten Kontaktfelder.
     *
     * Der Kaeufer duerfte sie hier sehen, aber die Kontaktdaten stehen ohnehin
     * aus dem Presenter daneben. Sie ein zweites Mal aus den Rohantworten zu
     * holen, waere ein Weg an der einen Stelle vorbei, an der ueber ihre
     * Sichtbarkeit entschieden wird.
     *
     * @return list<array{label: string, value: string}>
     */
    private function qualificationAnswers(LeadPurchase $purchase): array
    {
        // Beschriftungen aus der Fassung, unter der der Lead entstanden ist.
        // Ein Kaeufer soll lesen, was der Kunde angeklickt hat, nicht
        // "rasse_groesse: gross".
        $snapshot = $purchase->lead->funnelVersion?->snapshot;
        $labels = SnapshotLabels::questions(is_array($snapshot) ? $snapshot : null);
        $options = SnapshotLabels::options(is_array($snapshot) ? $snapshot : null);

        $answers = [];

        foreach ($purchase->lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $readable = static fn (mixed $single): string => $options[$answer->field_key][(string) $single]
                ?? (string) $single;

            $value = $answer->value;

            $answers[] = [
                'label' => $labels[$answer->field_key] ?? $answer->field_key,
                'value' => is_array($value)
                    ? implode(', ', array_map($readable, $value))
                    : $readable($value),
            ];
        }

        return $answers;
    }

    /**
     * "gekauft heute, 08:14", "gekauft gestern, 11:05", ab zwei Tagen das Datum.
     */
    private function relativeTime(?Carbon $moment): string
    {
        if ($moment === null) {
            return '-';
        }

        if ($moment->isToday()) {
            return (string) __('marketplace.purchased.bought_today', ['time' => $moment->format('H:i')]);
        }

        if ($moment->isYesterday()) {
            return (string) __('marketplace.purchased.bought_yesterday', ['time' => $moment->format('H:i')]);
        }

        return (string) __('marketplace.purchased.bought_on', ['date' => $moment->format('d.m.Y')]);
    }

    /** Sekunden als "3:42". */
    private function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function warn(string $message): void
    {
        $this->toastWarning($message);
    }
}
