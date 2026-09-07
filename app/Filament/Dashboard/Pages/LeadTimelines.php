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
 * Zeitverlauf der Leads (FB-071). Nur die Huelle fuer Routing und Navigation --
 * der Inhalt ist reines Livewire.
 */
class LeadTimelines extends Page
{
    protected string $view = 'filament.dashboard.pages.lead-timelines';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 4;

    public function getHeading(): string|Htmlable
    {
        return __('timeline.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('timeline.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('timeline.nav_label');
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
