<?php

namespace Tests\Feature\Http\Controllers;

use App\Livewire\Checkout\ProductCheckoutForm;
use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\OneTimeProductPrice;
use Tests\Feature\FeatureTest;

/**
 * Die Kaufseite eines Einmalprodukts -- im Portal die Guthabenaufladung.
 *
 * Geprueft wird gegen den Uebersetzungsschluessel und nicht gegen den
 * sichtbaren Text: Die Anwendung liefert auf Deutsch aus, und ein Test, der
 * eine Zeichenkette erwartet, bricht beim naechsten Textwechsel wieder.
 */
class ProductCheckoutControllerTest extends FeatureTest
{
    public function test_checkout_loads()
    {
        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-5'.rand(1, 1000),
            'is_active' => true,
            'max_quantity' => 1,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $response = $this->followingRedirects()->get(route('buy.product', [
            'productSlug' => $product->slug,
        ]));

        $response->assertStatus(200);

        $response->assertSee(__('marketplace.wallet.top_up.title'));
        // Die Menge ist aus der Oberflaeche heraus: Aufgeladen wird in festen
        // Paketen (6c0025e9). Der Wert entscheidet nur noch darueber, welche
        // Zahlungsanbieter angeboten werden.
        $response->assertSeeLivewire(ProductCheckoutForm::class);
    }

    public function test_checkout_quantity()
    {
        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-5'.rand(1, 100),
            'is_active' => true,
            'max_quantity' => 5,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $response = $this->followingRedirects()->get(route('buy.product', [
            'productSlug' => $product->slug,
        ]));

        $response->assertStatus(200);

        $response->assertSee(__('marketplace.wallet.top_up.title'));
        $response->assertSeeLivewire(ProductCheckoutForm::class);
    }
}
