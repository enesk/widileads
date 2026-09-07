<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Einstieg in den Funnel-Builder (FB-028).
 *
 * Anders als Builder, Regel- und Theme-Editor haengt diese Seite an keinem
 * konkreten Funnel und meldet sich deshalb zur Navigation an. Sie ist der
 * einzige Weg dorthin, der ohne Kenntnis der Adresse auskommt.
 */
class Funnels extends Page
{
    protected string $view = 'filament.dashboard.pages.funnels';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('builder.funnels.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('builder.funnels.nav_label');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getHeading(): string|Htmlable
    {
        return __('builder.funnels.heading');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if (! Gate::allows('funnels.manage', $tenant)) {
            return false;
        }

        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_FUNNELS,
        );
    }
}
