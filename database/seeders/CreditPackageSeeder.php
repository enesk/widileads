<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\OneTimeProductPrice;
use App\Services\CreditLedgerService;
use Illuminate\Database\Seeder;

/**
 * FB-052: Guthabenpakete als Einmalkauf-Produkte.
 *
 * Ein Paket ist ein gewoehnliches SaaSykit-Produkt; wie viel Guthaben darin
 * steckt, steht in seinem Metadatenfeld unter `credits`. Damit laeuft der Kauf
 * ueber die vorhandene Stripe-Anbindung, und es gibt keinen zweiten
 * Zahlungsweg -- die Buchung uebernimmt der Zuhoerer BookCreditsOnOrder.
 *
 * Bewusst nicht Teil des DatabaseSeeder: Preise und Zuschnitt der Pakete sind
 * eine Geschaeftsentscheidung und gehoeren ins Admin-Panel. Der Seeder legt
 * einen brauchbaren Ausgangsstand an und laesst sich gefahrlos wiederholen --
 * er aktualisiert vorhandene Pakete anhand ihres Slugs, statt neue anzulegen.
 *
 * Aufruf: php artisan db:seed --class=CreditPackageSeeder
 */
class CreditPackageSeeder extends Seeder
{
    /**
     * Anzahl Guthaben => Preis in der kleinsten Waehrungseinheit.
     *
     * Der Staffelpreis bildet den Vorgabepreis eines Leads (15,00 EUR aus
     * config('funnel.lead.default_price')) mit Mengenrabatt ab.
     *
     * @var array<int, array{name: string, credits: int, price: int}>
     */
    private const PACKAGES = [
        ['name' => '10 Leads', 'credits' => 10, 'price' => 15000],
        ['name' => '50 Leads', 'credits' => 50, 'price' => 70000],
        ['name' => '200 Leads', 'credits' => 200, 'price' => 260000],
    ];

    public function run(): void
    {
        $currency = Currency::query()->where('code', config('app.default_currency', 'EUR'))->first()
            ?? Currency::query()->first();

        if ($currency === null) {
            $this->command?->warn('Keine Waehrung vorhanden - bitte zuerst den CurrenciesSeeder ausfuehren.');

            return;
        }

        foreach (self::PACKAGES as $package) {
            $slug = 'lead-guthaben-'.$package['credits'];

            $product = OneTimeProduct::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $package['name'],
                    'description' => 'Guthaben fuer '.$package['credits'].' Leads im Marktplatz.',
                    'metadata' => [CreditLedgerService::PRODUCT_METADATA_KEY => $package['credits']],
                    'max_quantity' => 10,
                    'is_active' => true,
                    'is_visible' => true,
                ],
            );

            OneTimeProductPrice::query()->updateOrCreate(
                [
                    'one_time_product_id' => $product->getKey(),
                    'currency_id' => $currency->getKey(),
                ],
                ['price' => $package['price']],
            );
        }

        $this->command?->info(sprintf('%d Guthabenpakete angelegt oder aktualisiert.', count(self::PACKAGES)));
    }
}
