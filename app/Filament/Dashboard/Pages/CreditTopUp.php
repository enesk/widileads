<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\OneTimeProduct;
use App\Models\OneTimeProductPrice;
use App\Models\Tenant;
use App\Services\CreditLedgerService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Guthaben aufladen (FB-092).
 *
 * Zeigt die Guthabenpakete und fuehrt in den vorhandenen Einmalkauf-Checkout
 * von SaaSykit. Bezahlt wird dort ueber Stripe; gutgeschrieben wird das
 * Guthaben vom Zuhoerer BookCreditsOnOrder, sobald die Bestellung bezahlt ist.
 *
 * Diese Seite kauft also nichts und bucht nichts -- sie waehlt ein Produkt aus
 * und leitet weiter. Ein zweiter Zahlungsweg neben dem vorhandenen waere die
 * Stelle, an der Guthaben und Beleg irgendwann auseinanderlaufen.
 *
 * Ein Paket ist ein gewoehnliches Einmalkauf-Produkt; wie viel Guthaben darin
 * steckt, steht in seinem Metadatenfeld unter `credits` (FB-052). Gelistet
 * werden deshalb genau die aktiven Produkte mit diesem Schluessel -- neue
 * Pakete aus dem Admin-Bereich erscheinen hier von selbst.
 */
class CreditTopUp extends Page
{
    protected string $view = 'filament.dashboard.pages.credit-top-up';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'guthaben';
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.credit.top_up.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.credit.top_up.nav_label');
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
     * Der aktuelle Guthabenstand des Workspaces.
     */
    public function balance(): int
    {
        return app(CreditLedgerService::class)->balanceFor($this->tenant());
    }

    public function unitPrice(): float
    {
        return (float) config('funnel.marketplace.credit.unit_price');
    }

    /**
     * Die kaufbaren Pakete, aufsteigend nach Guthaben.
     *
     * @return list<array{slug: string, name: string, description: ?string, credits: int, price: float, url: string}>
     */
    public function packages(): array
    {
        $products = OneTimeProduct::query()
            ->where('is_active', true)
            ->get()
            ->filter(static fn (OneTimeProduct $product): bool => self::creditsOf($product) > 0);

        $prices = OneTimeProductPrice::query()
            ->whereIn('one_time_product_id', $products->pluck('id'))
            ->get()
            ->keyBy('one_time_product_id');

        $packages = [];

        foreach ($products as $product) {
            $price = $prices->get($product->getKey());

            // Ein Paket ohne Preis in dieser Waehrung ist nicht kaufbar. Es
            // auszublenden ist ehrlicher, als eine Schaltfläche anzubieten,
            // die im Checkout scheitert.
            if ($price === null) {
                continue;
            }

            $packages[] = [
                'slug' => (string) $product->slug,
                'name' => (string) $product->name,
                'description' => $product->description,
                'credits' => self::creditsOf($product),
                'price' => ((int) $price->price) / 100,
                'url' => route('buy.product', ['productSlug' => $product->slug]),
            ];
        }

        usort($packages, static fn (array $a, array $b): int => $a['credits'] <=> $b['credits']);

        return $packages;
    }

    private static function creditsOf(OneTimeProduct $product): int
    {
        return (int) (($product->metadata ?? [])[CreditLedgerService::PRODUCT_METADATA_KEY] ?? 0);
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
