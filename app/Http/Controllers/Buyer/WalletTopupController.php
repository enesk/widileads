<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Dto\CartItemDto;
use App\Http\Controllers\Controller;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CalculationService;
use App\Services\CheckoutService;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Nimmt den Aufladebetrag an und uebergibt an den vorhandenen Checkout
 * (LP-WALLET-009).
 *
 * Der Kaeufer waehlt einen freien Betrag in Euro. Abgerechnet wird ueber das
 * vorhandene Einmalkauf-Produkt von SaaSykit: Es kostet genau einen Euro, die
 * Menge im Warenkorb ist der Betrag in Euro. Damit gibt es weiterhin genau
 * einen Zahlungsweg, eine Bestellung und einen Beleg je Aufladung.
 *
 * Dieser Controller bucht nichts. Gutgeschrieben wird erst nach bestaetigter
 * Zahlung, und zwar vom Zuhoerer CreditWalletAfterPayment -- alles andere
 * hiesse Guthaben vor dem Geld.
 *
 * Nur ganze Euro: Cent-Betraege in der Menge waeren im Warenkorb nicht
 * darstellbar. Der Mindestbetrag (5.000 Cent) ist ohnehin ein glatter Betrag,
 * und in Cent aufzuladen verlangt niemand.
 *
 * **Ein Klick, eine Zahlung.** Der Kaeufer geht von der Aufladeseite direkt zum
 * Zahlungsanbieter. Die Checkout-Seite dazwischen hat fuer eine Aufladung
 * nichts zu fragen: Angemeldet ist er, Anschrift braucht der Warenkorb nicht,
 * und ihre einzige Eingabe war die Wahl des Anbieters -- die steht jetzt als
 * config('wallet.topup_payment_provider'). Bestellung, Beleg und Gutschrift
 * laufen unveraendert ueber dieselben Dienste wie vorher, es entfaellt nur der
 * Zwischenschritt.
 *
 * Zwei Faelle gehen weiterhin ueber die Checkout-Seite, und zwar absichtlich:
 * ein Anbieter, der seine Zahlung als Overlay statt als Weiterleitung anbietet
 * (Paddle braucht eine Seite, auf der sein Skript laeuft), und jede Stoerung
 * beim Anlegen der Bestellung. Ein Kaeufer mit Geld in der Hand landet dann auf
 * dem laengeren Weg, nicht in einer Fehlermeldung.
 */
class WalletTopupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $minEuro = (int) ceil(((int) config('wallet.topup_min_cents')) / 100);
        $maxEuro = intdiv((int) config('wallet.topup_max_cents'), 100);

        $data = $request->validate([
            'amount_euro' => ['required', 'integer', 'min:'.$minEuro, 'max:'.$maxEuro],
            'tenant' => ['required', 'string', Rule::exists('tenants', 'uuid')],
        ], [
            'amount_euro.min' => __('marketplace.wallet.top_up.errors.min', ['amount' => $minEuro]),
            'amount_euro.max' => __('marketplace.wallet.top_up.errors.max', ['amount' => $maxEuro]),
        ]);

        $tenant = $this->tenantOfUser($request, (string) $data['tenant']);

        $product = OneTimeProduct::query()
            ->where('slug', (string) config('wallet.topup_product_slug'))
            ->where('is_active', true)
            ->first();

        // Ohne Aufladeprodukt ist der Checkout nicht bedienbar. Das ist ein
        // Einrichtungsfehler des Portals und keine Fehleingabe des Kaeufers --
        // deshalb eine Meldung an ihn und kein 404 im Checkout.
        if (! $product instanceof OneTimeProduct) {
            return back()->withErrors([
                'amount_euro' => __('marketplace.wallet.top_up.errors.unavailable'),
            ]);
        }

        $quantity = (int) $data['amount_euro'];

        return $this->payDirectly($request, $product, $tenant, $quantity)
            // Der Workspace muss mit: Ohne ihn sucht sich der Checkout einen
            // beliebigen Workspace des Benutzers und legt notfalls einen neuen
            // an (FB-092). Das Guthaben landete dann dort, und der Kaeufer
            // saehe es nie.
            ?? redirect()->route('buy.product', [
                'productSlug' => $product->slug,
                'quantity' => $quantity,
                'tenant' => $tenant->uuid,
            ]);
    }

    /**
     * Legt Warenkorb und Bestellung an und leitet zum Zahlungsanbieter weiter.
     *
     * Gibt null zurueck, wenn dieser Weg nicht gangbar ist -- dann uebernimmt
     * die Checkout-Seite. Entschieden wird hier nichts, was dort anders
     * entschieden wuerde: Es sind dieselben Aufrufe in derselben Reihenfolge
     * wie in App\Livewire\Checkout\ProductCheckoutForm::checkout(), nur ohne
     * Anmeldung, Gutschein und Anbieterwahl.
     */
    private function payDirectly(Request $request, OneTimeProduct $product, Tenant $tenant, int $quantity): ?RedirectResponse
    {
        $slug = (string) config('wallet.topup_payment_provider');

        if ($slug === '') {
            return $this->fallback('kein Anbieter in wallet.topup_payment_provider hinterlegt', $slug);
        }

        try {
            $provider = app(PaymentService::class)->getPaymentProviderBySlug($slug);
        } catch (Throwable) {
            // Ein Tippfehler in der Konfiguration darf keine Aufladung
            // verhindern.
            return $this->fallback('Anbieter unbekannt', $slug);
        }

        // Ein Overlay-Anbieter braucht eine Seite, auf der sein Skript laeuft;
        // ein abgeschalteter Anbieter darf kein Geld nehmen. Beides endet auf
        // dem bisherigen Weg.
        if (! $provider->isRedirectProvider()) {
            return $this->fallback('Anbieter zahlt im Overlay und braucht eine Seite', $slug);
        }

        if (! $this->isActive($slug)) {
            return $this->fallback('Anbieter ist im Admin-Panel abgeschaltet', $slug);
        }

        $sessions = app(SessionService::class);

        $cartDto = $sessions->clearCartDto();
        $cartDto->tenantUuid = $tenant->uuid;

        $item = new CartItemDto;
        // Der Warenkorb haelt die Produktkennung als Text (CartItemDto), weil
        // er als reine Sitzungsdaten hin und her wandert.
        $item->productId = (string) $product->id;
        $item->quantity = $quantity;
        $cartDto->items[] = $item;

        $sessions->saveCartDto($cartDto);

        try {
            $totals = app(CalculationService::class)->calculateCartTotals($cartDto, $request->user());

            // Nichts zu zahlen heisst auch nichts aufzuladen -- das waere ein
            // Rechenfehler und kein Geschenk.
            if ($totals->amountDue <= 0) {
                return $this->fallback('offener Betrag ist null', $slug);
            }

            $order = app(CheckoutService::class)->initProductCheckout($cartDto, $tenant->uuid, $totals);

            if (! $order instanceof Order) {
                return $this->fallback('Bestellung wurde nicht angelegt', $slug);
            }

            // Die Bestellung gehoert in den Warenkorb, sonst legt die
            // Erfolgsseite sie nicht der Zahlung zu und der Kaeufer sieht
            // "unbekannte Bestellung".
            $cartDto->orderId = (string) $order->id;
            $sessions->saveCartDto($cartDto);

            $provider->initProductCheckout($order);

            return redirect()->away($provider->createProductCheckoutRedirectLink($order));
        } catch (Throwable $exception) {
            // Der Anbieter ist nicht erreichbar oder lehnt ab. Der Kaeufer soll
            // es auf dem bisherigen Weg versuchen koennen, die Ursache gehoert
            // ins Log.
            logger()->error('Aufladung: Zahlungsanbieter hat die Weiterleitung abgelehnt, Kaeufer geht ueber die Checkout-Seite.', [
                'provider' => $slug,
                'reason' => $exception->getMessage(),
            ]);

            // Der Warenkorb steht schon und traegt vielleicht auch schon die
            // Bestellung. Also auf die Checkout-Seite und nicht zurueck ueber
            // buy.product: Das leert den Warenkorb und legt eine zweite
            // Bestellung an, die als offene Zahlung liegen bleibt.
            return redirect()->route('checkout.product');
        }
    }

    /**
     * Schreibt auf, warum die Abkuerzung nicht genommen wurde, und gibt null
     * zurueck.
     *
     * Ohne diese Zeile ist der laengere Weg nicht von einem Fehler zu
     * unterscheiden: Der Kaeufer landet auf der Checkout-Seite, und niemand
     * sieht, ob der Anbieter abgeschaltet ist, falsch benannt oder gar keinen
     * Schluessel hat. Genau daran ist die Ursachensuche schon einmal
     * haengengeblieben.
     */
    private function fallback(string $reason, string $slug): null
    {
        logger()->warning('Aufladung laeuft ueber die Checkout-Seite statt direkt zum Zahlungsanbieter.', [
            'provider' => $slug,
            'reason' => $reason,
        ]);

        return null;
    }

    /**
     * Ist dieser Anbieter im Admin-Panel eingeschaltet?
     */
    private function isActive(string $slug): bool
    {
        return PaymentProvider::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Der Workspace, fuer den aufgeladen wird -- und zwar nur einer, dem der
     * angemeldete Benutzer angehoert. Eine fremde Kennung ist hier kein
     * Sonderfall, sondern der Versuch, fremdes Guthaben aufzuladen.
     */
    private function tenantOfUser(Request $request, string $uuid): Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $tenant = $user->tenants()->where('tenants.uuid', $uuid)->first();

        if (! $tenant instanceof Tenant) {
            abort(403);
        }

        return $tenant;
    }
}
