<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\LeadState;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\LeadListQuery;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lead-Liste des Betreibers (FB-034).
 *
 * Reines Livewire ohne Filament-Komponenten (Master-Prompt: kein Filament im
 * Tenant-Dashboard). Die Filter stehen in der Adresszeile, damit sich eine
 * Ansicht weitergeben laesst.
 *
 * Kontaktdaten kommen ausschliesslich ueber den LeadPresenter -- also bereits
 * fertig entschieden (FB-032). Die Liste selbst trifft keine Entscheidung
 * darueber, was jemand sehen darf.
 */
class LeadList extends Component
{
    use WithPagination;

    #[Url(as: 'funnel', except: '')]
    public string $funnelId = '';

    #[Url(as: 'zustand', except: '')]
    public string $leadState = '';

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    #[Url(as: 'plz', except: '')]
    public string $postalPrefix = '';

    #[Url(as: 'score_min', except: '')]
    public string $scoreMin = '';

    #[Url(as: 'score_max', except: '')]
    public string $scoreMax = '';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    /** Der geoeffnete Lead -- die Adresse bleibt damit teilbar. */
    #[Url(as: 'lead', except: null)]
    public ?int $selectedLeadId = null;

    /**
     * Jede Filteraenderung beginnt wieder auf Seite eins: Seite 7 eines
     * anderen Ergebnisses waere meistens leer.
     */
    public function updated(string $property): void
    {
        if ($property !== 'selectedLeadId') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['funnelId', 'leadState', 'from', 'until', 'postalPrefix', 'scoreMin', 'scoreMax', 'search']);
        $this->resetPage();
    }

    public function select(int $leadId): void
    {
        $this->selectedLeadId = $leadId;
    }

    public function closeDetail(): void
    {
        $this->selectedLeadId = null;
    }

    public function render(LeadListQuery $leads): View
    {
        $tenant = $this->tenant();
        $viewer = $this->viewer();

        /** @var LengthAwarePaginator<int, Lead> $page */
        $page = $leads->build($tenant, $viewer, [
            'funnel_id' => $this->funnelId,
            'lead_state' => $this->leadState,
            'from' => $this->from,
            'until' => $this->until,
            'postal_prefix' => $this->postalPrefix,
            'score_min' => $this->scoreMin,
            'score_max' => $this->scoreMax,
            'search' => $this->search,
        ])
            // Die Antworten werden fuer den Namen gebraucht -- ohne Eager
            // Loading eine Abfrage je Zeile.
            ->with(['answers', 'funnel'])
            ->paginate(25);

        return view('livewire.dashboard.lead-list', [
            'page' => $page,
            'rows' => $page->getCollection()->map(fn (Lead $lead): array => [
                'lead' => $lead,
                'presenter' => new LeadPresenter($lead, $viewer),
                'stale' => $leads->isStale($lead),
            ])->all(),
            'funnels' => Funnel::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->orderBy('name')
                ->pluck('name', 'id'),
            'states' => LeadState::labels(),
            'searchesContacts' => $leads->maySearchContacts($tenant, $viewer),
            'staleAfterDays' => (int) config('funnel.lead.stale_after_days'),
        ]);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function viewer(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
