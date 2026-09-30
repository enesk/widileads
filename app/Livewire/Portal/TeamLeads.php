<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\AuditAction;
use App\Constants\LeadContactStatus;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\Funnel;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\AuditLogger;
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
 * "Team Leads" im Portal -- alle Leadkaeufe des Mandanten (Ticket #3).
 *
 * **Diese Seite ist eine Auswertung, keine Arbeitsliste.** Sie beantwortet
 * "was hat mein Team gekauft, wer hat es gekauft, was hat es gekostet" --
 * "wo muss ich heute anrufen" beantwortet "Meine Leads". Daraus folgt der
 * ganze Aufbau: sortiert nach Kaufdatum, keine Dringlichkeitsgruppen, keine
 * Handlung an der Zeile und ausdruecklich kein Verweis auf die Detailseite.
 *
 * Gebaut neben App\Livewire\Portal\PurchasedLeads und nicht als Schalter
 * darin: Die beiden Seiten stellen verschiedene Fragen, und ein gemeinsamer
 * Zustand haette den Rechteunterschied in eine Verzweigung verwandelt.
 *
 * **Kontaktdaten bleiben verdeckt** -- Rufnummer und E-Mail, auch fuer einen
 * Admin. Wer den Kontakt braucht, geht ueber den Kollegen, der gekauft hat.
 * Entschieden wird das nicht hier, sondern im LeadContactResolver
 * (`forTeamView`), und die Zeile bekommt nur die fertige Fassung. Eine
 * Maskierung in dieser Komponente oder im Blade waere die Stelle, an der
 * irgendwann jemand ein Feld vergisst -- und die Rufnummer stuende trotzdem
 * im Livewire-Zustand.
 *
 * Der Export folgt derselben Regel: Er zeigt keine Spalte, die die Seite
 * verdeckt. Ein Export, der ausgibt, was die Ansicht zurueckhaelt, macht die
 * Regel zur Dekoration -- und er ist genau der Weg, den jemand nimmt, der die
 * Nummer trotzdem will.
 */
#[Layout('components.layouts.portal-app')]
class TeamLeads extends Component
{
    use InteractsWithPortalTenant;
    use WithPagination;

    /** Kaeuferfilter: alle. */
    public const BUYER_ALL = '';

    /** Kaeuferfilter: Kaeufe ohne handelnden Nutzer (Autokauf). */
    public const BUYER_AUTOMATIC = 'auto';

    public const PERIOD_7 = '7';

    public const PERIOD_30 = '30';

    public const PERIOD_90 = '90';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_ALL = 'all';

    public const STATUS_ALL = '';

    public const STATUS_OPEN = 'open';

    public const STATUS_REACHED = 'reached';

    public const STATUS_UNREACHED = 'unreached';

    /** Zeilen je Seite. */
    public const PER_PAGE = 25;

    #[Url(as: 'kaeufer', except: self::BUYER_ALL)]
    public string $buyer = self::BUYER_ALL;

    /**
     * Bewusst nicht "Alles": Eine Auswertungsseite, die beim Oeffnen die ganze
     * Historie laedt, wird mit jedem Monat langsamer und beantwortet die
     * haeufigste Frage nicht.
     */
    #[Url(as: 'zeitraum', except: self::PERIOD_30)]
    public string $period = self::PERIOD_30;

    #[Url(as: 'status', except: self::STATUS_ALL)]
    public string $status = self::STATUS_ALL;

    /**
     * Laeuft nach allen boot-Haken, der Mandant steht also bereits -- und bei
     * jeder Livewire-Folgeanfrage erneut. Ohne diese Wiederholung waere die
     * Rechtepruefung eine Pruefung beim ersten Aufruf und danach keine mehr.
     */
    public function booted(): void
    {
        $user = $this->portalUser();
        $tenant = $this->portalTenant();

        abort_unless($user instanceof User, 403);
        abort_unless(Gate::forUser($user)->allows('marketplace.access', $tenant), 403);
        abort_unless(Gate::forUser($user)->allows('lead-purchases.view-team', $tenant), 403);
    }

    /**
     * Ein geaenderter Filter beginnt wieder auf Seite eins -- sonst landet der
     * Admin auf einer Seite 3, die es nach dem Filtern nicht mehr gibt.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['buyer', 'period', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Zurueck auf die Vorgabe: letzte 30 Tage, alle Kaeufer, alle Status.
     */
    public function resetFilters(): void
    {
        $this->buyer = self::BUYER_ALL;
        $this->period = self::PERIOD_30;
        $this->status = self::STATUS_ALL;
        $this->resetPage();
    }

    public function render(): View
    {
        $tenant = $this->portalTenant();
        $purchases = $this->filteredQuery()
            ->orderByDesc('purchased_at')
            // Zweites Merkmal: Zwei Kaeufe in derselben Sekunde stuenden sonst
            // in zufaelliger Reihenfolge und sprangen beim Blaettern.
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.portal.team-leads', [
            'stats' => $this->stats(),
            'buyerOptions' => $this->buyerOptions(),
            'periodOptions' => $this->periodOptions(),
            'statusOptions' => $this->statusOptions(),
            'rows' => $this->rows($purchases),
            'purchases' => $purchases,
            // Zwei Leerzustaende: "im Zeitraum nichts" hat einen Weg heraus
            // (Filter weiten), "noch nie gekauft" hat einen anderen
            // (Marktplatz). Derselbe Kasten fuer beides waere in einem der
            // Faelle der falsche Rat.
            'hasAnyPurchase' => $this->baseQuery()->exists(),
            'workspaceName' => (string) $tenant->name,
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $tenant->uuid]),
        ])->title(__('marketplace.team_leads.heading'));
    }

    /**
     * Gibt die gefilterten Kaeufe als CSV aus -- mit derselben Maskierung wie
     * die Ansicht.
     *
     * Keine Rufnummer, keine E-Mail-Adresse. Festgehalten wird der Export
     * trotzdem im Audit-Log (FB-005): Wer Daten aus dem System traegt,
     * hinterlaesst eine Spur, auch wenn sie maskiert sind.
     */
    public function exportCsv(): StreamedResponse
    {
        $tenant = $this->portalTenant();
        $purchases = $this->filteredQuery()->orderByDesc('purchased_at')->get();

        app(AuditLogger::class)->log(
            AuditAction::DATA_EXPORTED,
            null,
            [
                'subject' => 'team_leads',
                'count' => $purchases->count(),
                'period' => $this->period,
                'buyer_filter' => $this->buyer,
            ],
            $tenant,
        );

        $rows = $purchases->map(function (LeadPurchase $purchase): array {
            $presenter = LeadPresenter::forTeamView($purchase->lead);

            return [
                $purchase->purchased_at?->format('d.m.Y H:i'),
                $presenter->name(),
                $this->funnelLabel($purchase->lead->funnel),
                $presenter->postalCode(),
                $this->statusLabel($purchase),
                Money::decimal((int) $purchase->price_cents),
                $this->buyerName($purchase),
            ];
        })->all();

        $headers = [
            __('marketplace.team_leads.csv.purchased_at'),
            __('marketplace.team_leads.csv.name'),
            __('marketplace.team_leads.csv.funnel'),
            __('marketplace.team_leads.csv.postal_code'),
            __('marketplace.team_leads.csv.status'),
            __('marketplace.team_leads.csv.price'),
            __('marketplace.team_leads.csv.buyer'),
        ];

        $filename = 'team-leads-'.now()->format('Y-m-d').'.csv';

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
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Die drei Zahlen oben -- immer zum gewaehlten Zeitraum und den gesetzten
     * Filtern, nie zum ganzen Bestand. Sonst widersprechen Kennzahl und Liste
     * einander, und der Admin glaubt der falschen von beiden.
     *
     * @return array{count: int, spent: string, top_buyer: string}
     */
    private function stats(): array
    {
        $query = $this->filteredQuery();

        $count = (clone $query)->count();
        $spent = (int) (clone $query)->sum('price_cents');

        return [
            'count' => $count,
            'spent' => Money::format($spent),
            'top_buyer' => $this->topBuyer(),
        ];
    }

    /**
     * Wer im Zeitraum am meisten gekauft hat.
     *
     * Autokaeufe zaehlen nicht mit: Sie haben keinen Menschen, und
     * "Automatisch" als aktivster Kaeufer waere eine Antwort auf eine andere
     * Frage. Bei Gleichstand gewinnt der alphabetisch erste, damit die Zahl
     * bei zwei Aufrufen nicht springt.
     */
    private function topBuyer(): string
    {
        $counts = $this->filteredQuery()
            ->whereNotNull('purchased_by_user_id')
            ->selectRaw('purchased_by_user_id, count(*) as purchase_count')
            ->groupBy('purchased_by_user_id')
            ->pluck('purchase_count', 'purchased_by_user_id');

        if ($counts->isEmpty()) {
            return '—';
        }

        $names = $this->members();

        $best = $counts
            ->map(static fn (int|string $total, int|string $userId): array => [
                'total' => (int) $total,
                'name' => (string) ($names[(int) $userId] ?? '—'),
            ])
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->first();

        return is_array($best) ? $best['name'] : '—';
    }

    /**
     * Die Zeilen der aktuellen Seite.
     *
     * @param  LengthAwarePaginator<int, LeadPurchase>  $purchases
     * @return list<array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $purchases): array
    {
        $userId = (int) ($this->portalUser()?->getKey() ?? 0);

        return collect($purchases->items())
            ->map(function (LeadPurchase $purchase) use ($userId): array {
                // Die Teamfassung: Name und Postleitzahl im Klartext,
                // Rufnummer und E-Mail verdeckt. Ohne Betrachter -- diese
                // Seite zeigt fuer jeden dasselbe.
                $presenter = LeadPresenter::forTeamView($purchase->lead);
                $buyerUser = $purchase->purchasedBy;

                return [
                    'id' => (int) $purchase->getKey(),
                    'date' => $purchase->purchased_at?->format('d.m.Y') ?? '—',
                    'name' => $presenter->name(),
                    'funnel' => $this->funnelLabel($purchase->lead->funnel),
                    'funnel_icon' => $this->funnelIcon($purchase->lead->funnel),
                    'postal_code' => $presenter->postalCode(),
                    'price' => Money::format((int) $purchase->price_cents),
                    'badge' => $this->badge($purchase),
                    // Die verdeckte Rufnummer steht sichtbar in der Zeile:
                    // Das ist der Unterschied zwischen "absichtlich verdeckt"
                    // und "steht nichts drin".
                    'phone_masked' => $presenter->phone(),
                    'buyer' => [
                        'automatic' => ! $buyerUser instanceof User,
                        'name' => $this->buyerName($purchase),
                        'initials' => $buyerUser instanceof User
                            ? $this->initials((string) $buyerUser->name)
                            : null,
                        'is_self' => $buyerUser instanceof User
                            && (int) $buyerUser->getKey() === $userId,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Der Name des Kaeufers -- oder "Automatisch" beim Autokauf.
     *
     * Ein Autokauf entscheidet ueber das Kaufprofil und nicht ueber einen
     * Menschen. Ein erfundener Name stuende hier als Tatsache.
     */
    private function buyerName(LeadPurchase $purchase): string
    {
        $user = $purchase->purchasedBy;

        if (! $user instanceof User) {
            return (string) __('marketplace.team_leads.buyer.automatic');
        }

        return (string) $user->name;
    }

    /**
     * Zwei Buchstaben fuer den Kreis in der Kaeufer-Zelle.
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
     * Die Statuspille -- dieselben Worte wie in "Meine Leads", damit dasselbe
     * Wort nicht zweimal etwas anderes bedeutet.
     *
     * @return array{label: string, tone: string, icon: string}
     */
    private function badge(LeadPurchase $purchase): array
    {
        return match ($purchase->lead->contact_status) {
            LeadContactStatus::BILLABLE => [
                'label' => (string) __('marketplace.purchased.tabs.reached'),
                'tone' => 'emerald',
                'icon' => 'check',
            ],
            LeadContactStatus::UNREACHABLE => [
                'label' => (string) __('marketplace.purchased.tabs.unreached'),
                'tone' => 'neutral',
                'icon' => 'phone-off',
            ],
            default => [
                'label' => (string) __('marketplace.purchased.tabs.open'),
                'tone' => 'amber',
                'icon' => 'clock',
            ],
        };
    }

    private function statusLabel(LeadPurchase $purchase): string
    {
        return $this->badge($purchase)['label'];
    }

    /**
     * Der Name des Fragebogens -- die Branche in der Zeile.
     *
     * Ein Lead ohne Fragebogen ist die Ausnahme (geloeschte Fassung), aber
     * eine leere Zelle liesse offen, ob die Angabe fehlt oder der Lead keine
     * hat.
     */
    private function funnelLabel(?Funnel $funnel): string
    {
        return $funnel instanceof Funnel
            ? (string) $funnel->name
            : (string) __('marketplace.listing.unknown_funnel');
    }

    /**
     * Ein Symbol je Funnel, abgeleitet wie im Marktplatz aus Name und
     * Kurzname. Ein eigenes Feld dafuer hat der Funnel nicht.
     */
    private function funnelIcon(?Funnel $funnel): string
    {
        if (! $funnel instanceof Funnel) {
            return 'grid';
        }

        $text = mb_strtolower($funnel->name.' '.$funnel->slug);

        return match (true) {
            str_contains($text, 'elektr') || str_contains($text, 'strom') => 'zap',
            str_contains($text, 'pfote') || str_contains($text, 'tier') || str_contains($text, 'hund') || str_contains($text, 'katze') => 'paw',
            str_contains($text, 'sanit') || str_contains($text, 'heiz') || str_contains($text, 'bad') => 'wrench',
            default => 'grid',
        };
    }

    /**
     * Die Auswahl im Kaeuferfilter: alle, dann jedes Mitglied mit mindestens
     * einem Kauf alphabetisch, zuletzt "Automatisch" -- und das nur, wenn es
     * solche Kaeufe gibt.
     *
     * Gezeigt werden nur Mitglieder mit Kaeufen: Ein Filter, der auf eine
     * garantiert leere Liste fuehrt, ist kein Filter.
     *
     * @return array<int|string, string>
     */
    private function buyerOptions(): array
    {
        $buyerIds = $this->baseQuery()
            ->whereNotNull('purchased_by_user_id')
            ->distinct()
            ->pluck('purchased_by_user_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $options = [self::BUYER_ALL => (string) __('marketplace.team_leads.filter.buyer_all')];

        foreach ($this->members() as $id => $name) {
            if (in_array($id, $buyerIds, true)) {
                $options[(string) $id] = $name;
            }
        }

        $hasAutomatic = $this->baseQuery()->whereNull('purchased_by_user_id')->exists();

        if ($hasAutomatic) {
            $options[self::BUYER_AUTOMATIC] = (string) __('marketplace.team_leads.buyer.automatic');
        }

        return $options;
    }

    /**
     * Die Mitglieder des Mandanten, alphabetisch: Id => Name.
     *
     * @return array<int, string>
     */
    private function members(): array
    {
        return $this->portalTenant()
            ->users()
            ->orderBy('name')
            ->pluck('name', 'users.id')
            ->map(static fn (?string $name): string => $name ?? '—')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function periodOptions(): array
    {
        return [
            self::PERIOD_7 => (string) __('marketplace.team_leads.filter.period.7'),
            self::PERIOD_30 => (string) __('marketplace.team_leads.filter.period.30'),
            self::PERIOD_90 => (string) __('marketplace.team_leads.filter.period.90'),
            self::PERIOD_MONTH => (string) __('marketplace.team_leads.filter.period.month'),
            self::PERIOD_LAST_MONTH => (string) __('marketplace.team_leads.filter.period.last_month'),
            self::PERIOD_ALL => (string) __('marketplace.team_leads.filter.period.all'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            self::STATUS_ALL => (string) __('marketplace.purchased.tabs.all'),
            self::STATUS_OPEN => (string) __('marketplace.purchased.tabs.open'),
            self::STATUS_REACHED => (string) __('marketplace.purchased.tabs.reached'),
            self::STATUS_UNREACHED => (string) __('marketplace.purchased.tabs.unreached'),
        ];
    }

    /**
     * Die Abfrage mit allen gesetzten Filtern.
     *
     * @return Builder<LeadPurchase>
     */
    private function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->buyer === self::BUYER_AUTOMATIC) {
            $query->whereNull('purchased_by_user_id');
        } elseif ($this->buyer !== self::BUYER_ALL && ctype_digit($this->buyer)) {
            $query->where('purchased_by_user_id', (int) $this->buyer);
        }

        $since = $this->periodStart();

        if ($since instanceof Carbon) {
            $query->where('purchased_at', '>=', $since);
        }

        $until = $this->periodEnd();

        if ($until instanceof Carbon) {
            $query->where('purchased_at', '<', $until);
        }

        $contactStatus = match ($this->status) {
            self::STATUS_OPEN => LeadContactStatus::OPEN,
            self::STATUS_REACHED => LeadContactStatus::BILLABLE,
            self::STATUS_UNREACHED => LeadContactStatus::UNREACHABLE,
            default => null,
        };

        if ($contactStatus !== null) {
            $query->whereHas(
                'lead',
                static fn (Builder $lead): Builder => $lead->where('contact_status', $contactStatus),
            );
        }

        return $query;
    }

    private function periodStart(): ?Carbon
    {
        return match ($this->period) {
            // Die Tageszahl steht im Filterwert selbst -- ein zweites Mal als
            // Zahl hier waere eine Stelle, die auseinanderlaufen kann.
            self::PERIOD_7, self::PERIOD_90 => now()->subDays((int) $this->period)->startOfDay(),
            self::PERIOD_MONTH => now()->startOfMonth(),
            self::PERIOD_LAST_MONTH => now()->subMonthNoOverflow()->startOfMonth(),
            self::PERIOD_ALL => null,
            // Auch jeder unbekannte Wert aus der Adresszeile landet hier: Die
            // Vorgabe ist der engere Zeitraum, nicht der weitere.
            default => now()->subDays((int) self::PERIOD_30)->startOfDay(),
        };
    }

    /**
     * Nur "Letzter Monat" hat ein Ende -- alle anderen Zeitraeume laufen bis
     * jetzt.
     */
    private function periodEnd(): ?Carbon
    {
        return $this->period === self::PERIOD_LAST_MONTH
            ? now()->startOfMonth()
            : null;
    }

    /**
     * Alle Kaeufe des aktiven Mandanten -- ohne Einschraenkung auf den Nutzer.
     * Genau das ist der Unterschied zu "Meine Leads", und das Recht
     * `view team leads` ist die Bedingung dafuer (geprueft in booted()).
     *
     * @return Builder<LeadPurchase>
     */
    private function baseQuery(): Builder
    {
        return LeadPurchase::query()
            ->with([
                'purchasedBy',
                'lead.answers',
                // Ohne Mandanten-Scope: Fragebogen und Fassung gehoeren dem
                // Betreiber, nicht dem Kaeufer. Mit Scope kaeme hier immer
                // null heraus, und die Zeilen stuenden ohne Herkunft da.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names()),
            ])
            ->ofBuyer($this->portalTenant());
    }
}
