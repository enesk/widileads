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
     * Die Pakete, die ein Kaeufer aufladen kann.
     *
     * Ein Guthaben kostet 5,00 EUR (config('funnel.marketplace.credit.unit_price')). Die
     * Guthabenzahl steht deshalb nicht frei in dieser Liste, sondern wird aus
     * dem Betrag errechnet -- so koennen Preis und Guthaben nicht
     * auseinanderlaufen.
     *
     * @var array<int, array{name: string, price: int}> Preis in Cent
     */
    private const PACKAGES = [
        ['name' => 'Guthaben 500 EUR', 'price' => 50000],
        ['name' => 'Guthaben 1.000 EUR', 'price' => 100000],
        ['name' => 'Guthaben 1.500 EUR', 'price' => 150000],
    ];

    public function run(): void
    {
        $currency = Currency::query()->where('code', config('app.default_currency', 'EUR'))->first()
            ?? Currency::query()->first();

        if ($currency === null) {
            $this->command?->warn('Keine Waehrung vorhanden - bitte zuerst den CurrenciesSeeder ausfuehren.');

            return;
        }

        $unitPriceCents = (int) round(((float) config('funnel.marketplace.credit.unit_price')) * 100);

        if ($unitPriceCents <= 0) {
            $this->command?->warn('funnel.marketplace.credit.unit_price ist nicht gesetzt - die Pakete wurden uebersprungen.');

            return;
        }

        foreach (self::PACKAGES as $package) {
            // Guthaben aus dem Betrag: 500,00 EUR zu 5,00 EUR je Guthaben
            // ergibt 100. Waere die Zahl von Hand gepflegt, muesste sie bei
            // jeder Preisaenderung mitgepflegt werden.
            $credits = intdiv($package['price'], $unitPriceCents);
            $slug = 'lead-guthaben-'.$credits;

            $product = OneTimeProduct::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $package['name'],
                    'description' => $credits.' Guthaben fuer den Leadkauf im Marktplatz.',
                    'metadata' => [CreditLedgerService::PRODUCT_METADATA_KEY => $credits],
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
