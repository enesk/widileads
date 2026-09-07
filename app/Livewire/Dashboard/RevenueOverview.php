<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Tenant;
use App\Services\OperatorRevenueReport;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Umsatzuebersicht des Betreibers (FB-072).
 *
 * Reines Livewire ohne Filament-Komponenten.
 */
class RevenueOverview extends Component
{
    #[Url(as: 'von', except: '')]
    public string $from = '';

    #[Url(as: 'bis', except: '')]
    public string $until = '';

    public function render(OperatorRevenueReport $report): View
    {
        return view('livewire.dashboard.revenue-overview', [
            'report' => $report->for(
                $this->tenant(),
                $this->from === '' ? null : Carbon::parse($this->from)->startOfDay(),
                $this->until === '' ? null : Carbon::parse($this->until)->endOfDay(),
            ),
        ]);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
