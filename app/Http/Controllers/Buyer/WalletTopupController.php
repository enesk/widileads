<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\OneTimeProduct;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // Der Workspace muss mit: Ohne ihn sucht sich der Checkout einen
        // beliebigen Workspace des Benutzers und legt notfalls einen neuen an
        // (FB-092). Das Guthaben landete dann dort, und der Kaeufer saehe es nie.
        return redirect()->route('buy.product', [
            'productSlug' => $product->slug,
            'quantity' => (int) $data['amount_euro'],
            'tenant' => $tenant->uuid,
        ]);
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
