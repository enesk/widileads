<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Tenant;
use App\Models\Wallet;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Guthaben aufladen (LP-WALLET-009, vormals FB-092).
 *
 * Der Kaeufer waehlt einen freien Betrag in Euro -- ab
 * config('wallet.topup_min_cents'), mit den Vorschlaegen aus
 * config('wallet.topup_presets_cents'). Die bisherigen Guthabenpakete sind
 * entfallen: Das Wallet rechnet in Geld, ein Paket mit einer Guthabenzahl
 * beschreibt nichts mehr.
 *
 * Seit LP-WALLET-011 traegt die Seite ausserdem den Transaktionsverlauf: Der
 * Kaeufer sieht seinen Stand, laedt auf und liest nach, wo sein Geld geblieben
 * ist -- alles an einer Stelle. Der Klassenname bleibt WalletTopUp, weil
 * Adresse (`guthaben`), Bestaetigungsmail und der Hinweis im Marktplatz auf ihn
 * zeigen.
 *
 * Diese Seite bucht nichts und rechnet nichts ab. Das Formular geht an
 * WalletTopupController, der in den vorhandenen Einmalkauf-Checkout von
 * SaaSykit uebergibt; gutgeschrieben wird nach bestaetigter Zahlung vom
 * Zuhoerer CreditWalletAfterPayment. Ein zweiter Zahlungsweg neben dem
 * vorhandenen waere die Stelle, an der Geld und Beleg auseinanderlaufen.
 */
class WalletTopUp extends Page
{
    protected string $view = 'filament.dashboard.pages.wallet-top-up';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'guthaben';
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.wallet.buyer.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.wallet.buyer.nav_label');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && Gate::allows('marketplace.access', $tenant);
    }

    /**
     * Das Kauf-Wallet dieses Workspaces. Salden liest die Seite vom Wallet,
     * nicht aus einer eigenen Summe -- die Wahrheit bleibt das Journal.
     */
    public function wallet(): Wallet
    {
        return Wallet::forBuyer($this->tenant());
    }

    /**
     * Kleinster zulaessiger Betrag in ganzen Euro.
     */
    public function minEuro(): int
    {
        return (int) ceil(((int) config('wallet.topup_min_cents')) / 100);
    }

    /**
     * Groesster zulaessiger Betrag in ganzen Euro.
     */
    public function maxEuro(): int
    {
        return intdiv((int) config('wallet.topup_max_cents'), 100);
    }

    /**
     * Die Vorschlaege in ganzen Euro, aufsteigend. Ein Vorschlag unter dem
     * Mindestbetrag wird nicht angeboten -- er fuehrte nur in die
     * Fehlermeldung des Controllers.
     *
     * @return list<int>
     */
    public function presetsEuro(): array
    {
        $presets = array_map(
            static fn (int $cents): int => intdiv($cents, 100),
            array_map('intval', (array) config('wallet.topup_presets_cents', [])),
        );

        $presets = array_values(array_filter(
            array_unique($presets),
            fn (int $euro): bool => $euro >= $this->minEuro() && $euro <= $this->maxEuro(),
        ));

        sort($presets);

        return $presets;
    }

    public function currentTenant(): Tenant
    {
        return $this->tenant();
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            abort(403);
        }

        return $tenant;
    }
}
