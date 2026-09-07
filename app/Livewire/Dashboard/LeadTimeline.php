<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\LeadState;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\LeadTimelineReport;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Zeitverlauf der Leads im Betreiber-Dashboard (FB-071).
 *
 * Reines Livewire ohne Filament-Komponenten. Die Auswahl steht in der
 * Adresszeile, damit sich eine Ansicht weitergeben laesst.
 */
class LeadTimeline extends Component
{
    #[Url(as: 'raster', except: 'day')]
    public string $granularity = 'day';

    #[Url(as: 'nach', except: 'none')]
    public string $breakdown = 'none';

    #[Url(as: 'funnel', except: '')]
    public string $funnelId = '';

    #[Url(as: 'zustand', except: '')]
    public string $leadState = '';

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    public function render(LeadTimelineReport $report): View
    {
        $tenant = $this->tenant();

        return view('livewire.dashboard.lead-timeline', [
            'report' => $report->for($tenant, $this->granularity, $this->breakdown, [
                'funnel_id' => $this->funnelId,
                'lead_state' => $this->leadState,
                'from' => $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
                'until' => $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
            ]),
            'funnels' => Funnel::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->orderBy('name')
                ->pluck('name', 'id'),
            'states' => LeadState::labels(),
            'granularities' => LeadTimelineReport::GRANULARITIES,
            'breakdowns' => LeadTimelineReport::BREAKDOWNS,
        ]);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
