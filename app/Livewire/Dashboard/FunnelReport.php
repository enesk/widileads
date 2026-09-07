<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\FunnelConversionReport;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Trichter eines Funnels im Betreiber-Dashboard (FB-070).
 *
 * Reines Livewire ohne Filament-Komponenten. Die Auswahl steht in der
 * Adresszeile, damit sich eine Ansicht weitergeben laesst.
 */
class FunnelReport extends Component
{
    #[Url(as: 'funnel', except: '')]
    public string $funnelId = '';

    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    public function render(FunnelConversionReport $report): View
    {
        $tenant = $this->tenant();
        $funnels = $this->funnelsOf($tenant);

        if ($this->funnelId === '' && $funnels->isNotEmpty()) {
            $this->funnelId = (string) $funnels->keys()->first();
        }

        $funnel = $this->funnelId === ''
            ? null
            : Funnel::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->with('currentVersion')
                ->find($this->funnelId);

        return view('livewire.dashboard.funnel-report', [
            'funnels' => $funnels,
            'funnel' => $funnel,
            'report' => $funnel === null ? null : $report->for(
                $funnel,
                $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
                $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
            ),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function funnelsOf(Tenant $tenant)
    {
        return Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
