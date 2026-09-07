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
 * Lead-Liste des Betreibers im Dashboard (FB-034).
 *
 * Nur die Huelle fuer Routing und Navigation -- der Inhalt ist reines Livewire.
 * Kaeufer-Workspaces sehen die Seite nicht: Sie arbeiten mit dem Marktplatz
 * (FB-053) und mit ihren gekauften Leads (FB-057), nicht mit den Anfragen eines
 * fremden Betreibers.
 */
class Leads extends Page
{
    protected string $view = 'filament.dashboard.pages.leads';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    public function getHeading(): string|Htmlable
    {
        return __('leads.list.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('leads.list.heading');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.leads');
    }

    public static function getNavigationLabel(): string
    {
        return __('leads.list.nav_label');
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
