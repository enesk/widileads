<?php

declare(strict_types=1);

namespace App\Listeners\Order;

use App\Events\Order\Ordered;
use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Services\CreditLedgerService;

/**
 * Schreibt das gekaufte Guthaben gut, sobald eine Bestellung bezahlt ist (FB-052).
 *
 * Guthabenpakete sind gewoehnliche Einmalkauf-Produkte von SaaSykit; wie viel
 * Guthaben in einem Paket steckt, steht in seinem Metadatenfeld unter
 * `credits`. Damit laeuft der Kauf ueber die vorhandene Stripe-Anbindung, und
 * es gibt keinen zweiten Zahlungsweg.
 *
 * Bewusst **nicht** in die Queue gegeben: Zwischen bezahlter Bestellung und
 * gutgeschriebenem Guthaben soll nichts liegen, das fehlschlagen und
 * unbemerkt liegenbleiben kann. Scheitert die Buchung, scheitert die
 * Webhook-Verarbeitung -- und Stripe stellt erneut zu. Dass daraus keine
 * doppelte Gutschrift wird, sichert der Beleg: Die Buchung traegt die
 * Bestellung als Referenz und ist ueber den Unique-Index auf
 * (type, reference_type, reference_id) idempotent.
 */
class BookCreditsOnOrder
{
    public function __construct(private readonly CreditLedgerService $creditLedger) {}

    public function handle(Ordered $event): void
    {
        $order = $event->order;

        // Bestellungen ohne Mandantenbezug gehoeren keinem Guthabenkonto.
        $tenant = $order->tenant_id === null ? null : Tenant::query()->find($order->tenant_id);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $credits = $this->creditsIn($order);

        if ($credits <= 0) {
            return;
        }

        $this->creditLedger->purchase(
            $tenant,
            $credits,
            (int) $order->total_amount_after_discount,
            $order,
            // Die Waehrung der Bestellung, nicht die Standardwaehrung: Der
            // Betrag stammt aus dieser Bestellung, und ihn unter einer anderen
            // Waehrung abzulegen waere genau der Fehler, den die Spalte
            // verhindern soll (FB-052a).
            $this->currencyOf($order),
        );
    }

    /**
     * Waehrung, in der die Bestellung bezahlt wurde. Ohne hinterlegte Waehrung
     * entscheidet der Buchungsdienst mit der Standardwaehrung.
     */
    private function currencyOf(Order $order): ?string
    {
        if ($order->currency_id === null) {
            return null;
        }

        $code = Currency::query()->whereKey($order->currency_id)->value('code');

        return is_string($code) ? $code : null;
    }

    /**
     * Summe der Guthaben ueber alle Positionen der Bestellung. Produkte ohne
     * das Metadatenfeld tragen nichts bei -- eine Bestellung darf Guthaben und
     * anderes mischen.
     *
     * Positionen und Produkte werden bewusst direkt abgefragt statt ueber die
     * Beziehungen von Order: deren Typen sind im Bestandscode nicht ausgezeichnet,
     * und sie nachzuziehen legt in der Zahlungsanbindung eine Reihe bislang
     * verdeckter Typfehler frei -- fremder Umfang. Ein Vermerk dazu steht in
     * docs/BACKLOG.md.
     */
    private function creditsIn(Order $order): int
    {
        $items = OrderItem::query()->where('order_id', $order->getKey())->get();

        if ($items->isEmpty()) {
            return 0;
        }

        // Ein Zugriff je Bestellung statt einer je Position: Produktkennung auf
        // enthaltenes Guthaben. Ein Produkt ohne das Metadatenfeld traegt null bei.
        $creditsPerProduct = OneTimeProduct::query()
            ->whereIn('id', $items->pluck('one_time_product_id')->all())
            ->get()
            ->mapWithKeys(static fn (OneTimeProduct $product): array => [
                (int) $product->getKey() => (int) (($product->metadata ?? [])[CreditLedgerService::PRODUCT_METADATA_KEY] ?? 0),
            ])
            ->all();

        $credits = 0;

        foreach ($items as $item) {
            $perUnit = $creditsPerProduct[(int) $item->one_time_product_id] ?? 0;

            if ($perUnit > 0) {
                $credits += $perUnit * (int) $item->quantity;
            }
        }

        return $credits;
    }
}
