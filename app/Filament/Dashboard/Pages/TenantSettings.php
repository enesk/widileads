<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class TenantSettings extends Page
{
    protected string $view = 'filament.dashboard.pages.tenant-settings';

    public function getHeading(): string|Htmlable
    {
        return __('Workspace Settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Workspace Settings');
    }

    public static function canAccess(): bool
    {
        $tenantPermissionService = app(TenantPermissionService::class); // a bit ugly, but this is the Filament way :/

        return $tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
        );
    }

    /**
     * Zeigt diese Seite den Leadpreis? Nur fuer Verkaeufer -- ein Kaeufer
     * verkauft keine Leads (LP-WALLET-012). Dieselbe Bedingung wie bei der
     * Bankverbindung im Formular darunter.
     */
    public function showsLeadPrice(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && ! $tenant->isBuyer();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
