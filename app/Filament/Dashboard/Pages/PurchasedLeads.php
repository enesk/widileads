<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Tenant;
use App\Services\TenantTypeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * "Meine Leads" -- die gekauften Leads eines Kaeufers (FB-057).
 *
 * Nur die Huelle: Routing, Navigation, Titel, Zugriffspruefung.
 */
class PurchasedLeads extends Page
{
    protected string $view = 'filament.dashboard.pages.purchased-leads';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = -9;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.purchased.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.purchased.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.purchased.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantTypeService::class)->canAccessMarketplace($tenant);
    }
}
