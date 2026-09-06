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
 * Der Lead-Marktplatz (FB-053).
 *
 * Nur die Huelle: Routing, Navigationseintrag, Titel und Zugriffspruefung. Der
 * Inhalt ist eine gewoehnliche Livewire-Komponente.
 *
 * Sichtbar nur fuer freigeschaltete Kaeufer -- geprueft ueber dieselbe Methode
 * wie Gate und Middleware aus FB-050, nicht ueber eine eigene Bedingung.
 */
class Marketplace extends Page
{
    protected string $view = 'filament.dashboard.pages.marketplace';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = -10;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.listing.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.listing.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.listing.nav_label');
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
