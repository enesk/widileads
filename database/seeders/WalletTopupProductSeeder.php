<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\OneTimeProductPrice;
use Illuminate\Database\Seeder;

/**
 * LP-WALLET-009: Das Einmalkauf-Produkt hinter der Wallet-Aufladung.
 *
 * Der Kaeufer waehlt einen freien Betrag; abgerechnet wird er ueber den
 * vorhandenen SaaSykit-Checkout. Damit ein freier Betrag durch einen Checkout
 * passt, der nur feste Produktpreise kennt, kostet dieses Produkt genau einen
 * Euro und die Menge im Warenkorb ist der Betrag in Euro. Ein zweiter
 * Zahlungsweg neben dem vorhandenen waere die Stelle, an der Geld und Beleg
 * auseinanderlaufen.
 *
 * Das Produkt ist nicht sichtbar (`is_visible = false`): Es gehoert nicht in
 * die oeffentliche Produktliste, sondern nur hinter die Aufladeseite.
 *
 * Zugleich werden die alten Guthabenpakete (FB-052) stillgelegt. Geloescht
 * werden sie nicht -- an ihnen haengen bezahlte Bestellungen, und eine
 * Bestellhistorie ohne Produkt ist wertlos. Deaktiviert sind sie nicht mehr
 * kaufbar, und der Checkout weist sie ab.
 *
 * Aufruf: php artisan db:seed --class=WalletTopupProductSeeder
 */
class WalletTopupProductSeeder extends Seeder
{
    /**
     * Metadatenfeld, in dem ein Guthabenpaket aus FB-052 seine Guthabenzahl
     * trug. Daran sind Altpakete erkennbar.
     */
    private const LEGACY_CREDITS_METADATA_KEY = 'credits';

    public function run(): void
    {
        $currency = Currency::query()->where('code', config('wallet.currency', 'EUR'))->first()
            ?? Currency::query()->first();

        if ($currency === null) {
            $this->command?->warn('Keine Waehrung vorhanden - bitte zuerst den CurrenciesSeeder ausfuehren.');

            return;
        }

        $slug = (string) config('wallet.topup_product_slug');

        // Die Hoechstmenge ist der Hoechstbetrag in Euro: Der Checkout kappt die
        // Menge stillschweigend auf max_quantity, eine zu kleine Grenze wuerde
        // also weniger abrechnen als der Kaeufer gewaehlt hat.
        $maxQuantity = intdiv((int) config('wallet.topup_max_cents'), 100);

        $product = OneTimeProduct::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => 'Guthaben-Aufladung',
                'description' => 'Guthaben fuer den Leadkauf im Marktplatz. Der Betrag wird nach bestaetigter Zahlung sofort gutgeschrieben.',
                'metadata' => null,
                'max_quantity' => $maxQuantity,
                'is_active' => true,
                'is_visible' => false,
            ],
        );

        OneTimeProductPrice::query()->updateOrCreate(
            [
                'one_time_product_id' => $product->getKey(),
                'currency_id' => $currency->getKey(),
            ],
            ['price' => 100],
        );

        $this->deactivateLegacyCreditPackages($slug);

        $this->command?->info('Aufladeprodukt "'.$slug.'" angelegt oder aktualisiert.');
    }

    /**
     * Die Guthabenpakete aus FB-052 stilllegen: Ein Paket traegt seine
     * Guthabenzahl im Metadatenfeld, daran sind sie erkennbar.
     *
     * LP-WALLET-018: Das alte Guthabensystem ist abgebaut, der Schluessel wird
     * hier aber weiterhin gebraucht. Ein Altpaket, das aktiv im Shop stehen
     * bleibt, laesst sich kaufen und bucht nichts mehr -- der Kaeufer zahlt
     * dann fuer nichts. Die Erkennung muss den Abbau also ueberleben, deshalb
     * steht der Metadatenschluessel jetzt hier statt im entfernten
     * Guthabendienst.
     */
    private function deactivateLegacyCreditPackages(string $topupSlug): void
    {
        $packages = OneTimeProduct::query()
            ->where('slug', '!=', $topupSlug)
            ->where('is_active', true)
            ->get()
            ->filter(static fn (OneTimeProduct $product): bool => (int) (($product->metadata ?? [])[self::LEGACY_CREDITS_METADATA_KEY] ?? 0) > 0);

        foreach ($packages as $package) {
            $package->update(['is_active' => false, 'is_visible' => false]);
        }

        if ($packages->isNotEmpty()) {
            $this->command?->info(sprintf('%d altes Guthabenpaket stillgelegt.', $packages->count()));
        }
    }
}
