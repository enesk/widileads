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
 * Kaufkriterien des Kaeufer-Mandanten (FB-051).
 *
 * Nur die Huelle: Routing, Navigationseintrag, Titel und Zugriffspruefung. Der
 * Inhalt ist eine gewoehnliche Livewire-Komponente.
 *
 * Die Seite sieht nur, wer den Marktplatz sehen darf -- also ein
 * freigeschalteter Kaeufer (FB-050). Geprueft wird ueber dieselbe Methode wie
 * Gate und Middleware, nicht ueber eine eigene Bedingung.
 */
class BuyerProfile extends Page
{
    protected string $view = 'filament.dashboard.pages.buyer-profile';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.profile.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.profile.heading');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.profile.nav_label');
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
