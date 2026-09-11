<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Tenant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Einnahmen und Auszahlung des Verkaeufers (LP-WALLET-012).
 *
 * Die Seite selbst haelt keinen Zustand und rechnet nichts: Sie bindet die
 * beiden Livewire-Komponenten ein, die Salden anzeigen
 * (App\Livewire\Seller\SellerWallet) und Auszahlungen anfordern
 * (App\Livewire\Seller\PayoutRequestForm). Der Leadpreis gehoert nicht hierher,
 * sondern zu den Workspace-Einstellungen neben die Bankverbindung.
 *
 * Zugang haben Mandanten, die Funnels verwalten -- das sind die Verkaeufer.
 * Ein Kaeufer-Mandant hat kein Verkaufs-Wallet und bekommt die Seite nicht zu
 * sehen.
 */
class SellerEarnings extends Page
{
    protected string $view = 'filament.dashboard.pages.seller-earnings';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 6;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'einnahmen';
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.wallet.seller.title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.wallet.seller.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.wallet.seller.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && Gate::allows('funnels.manage', $tenant);
    }
}
