<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Trichter je Funnel (FB-070). Nur die Huelle fuer Routing und Navigation --
 * der Inhalt ist reines Livewire.
 */
class FunnelReports extends Page
{
    protected string $view = 'filament.dashboard.pages.funnel-reports';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 3;

    public function getHeading(): string|Htmlable
    {
        return __('reports.funnel.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('reports.funnel.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('reports.funnel.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->user() instanceof User) {
            return false;
        }

        return app(TenantTypeService::class)->canManageFunnels($tenant);
    }
}
