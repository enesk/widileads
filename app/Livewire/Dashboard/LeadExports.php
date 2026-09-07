<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\LeadExportStatus;
use App\Jobs\BuildLeadExport;
use App\Models\LeadExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeadExportBuilder;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Exporte des Betreibers anfordern und herunterladen (FB-073).
 *
 * Reines Livewire ohne Filament-Komponenten.
 */
class LeadExports extends Component
{
    /**
     * @var list<string>
     */
    public array $columns = ['id', 'created_at', 'funnel', 'lead_state', 'score', 'name', 'email', 'phone', 'postal_code'];

    public function requestExport(): void
    {
        $tenant = $this->tenant();
        $viewer = $this->viewer();

        $columns = array_values(array_intersect(LeadExportBuilder::COLUMNS, $this->columns));

        if ($columns === []) {
            $this->addError('columns', __('exports.errors.no_columns'));

            return;
        }

        $export = LeadExport::query()->create([
            'tenant_id' => $tenant->getKey(),
            'requested_by' => $viewer->getKey(),
            'columns' => $columns,
            'filters' => [],
        ]);

        BuildLeadExport::dispatch((int) $export->getKey());
    }

    public function render(): View
    {
        return view('livewire.dashboard.lead-exports', [
            'exports' => LeadExport::query()
                ->where('tenant_id', $this->tenant()->getKey())
                ->with('requester')
                ->latest('id')
                ->limit((int) config('funnel.export.history_size'))
                ->get()
                ->map(fn (LeadExport $export): array => [
                    'export' => $export,
                    'download' => $export->isDownloadable() ? $this->downloadLink($export) : null,
                ])
                ->all(),
            'availableColumns' => LeadExportBuilder::COLUMNS,
            'ready' => LeadExportStatus::READY,
        ]);
    }

    private function downloadLink(LeadExport $export): string
    {
        return URL::temporarySignedRoute(
            'lead-export.download',
            now()->addMinutes((int) config('funnel.export.link_ttl_minutes')),
            ['export' => $export->uuid],
        );
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
