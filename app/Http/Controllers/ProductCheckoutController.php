<?php

namespace App\Http\Controllers;

use App\Constants\OrderStatus;
use App\Dto\CartItemDto;
use App\Models\OneTimeProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Services\CreditLedgerService;
use App\Services\DiscountService;
use App\Services\OneTimeProductService;
use App\Services\SessionService;

class ProductCheckoutController extends Controller
{
    public function __construct(
        private DiscountService $discountService,
        private OneTimeProductService $productService,
        private SessionService $sessionService,
    ) {}

    public function productCheckout()
    {
        $cartDto = $this->sessionService->getCartDto();

        if (empty($cartDto->items)) {
            return redirect()->route('home');
        }

        return view('checkout.product');
    }

    public function addToCart(string $productSlug, int $quantity = 1)
    {
        $cartDto = $this->sessionService->clearCartDto();  // use getCartDto() instead of clearCartDto() when allowing full cart checkout with multiple items

        // FB-092: Der Workspace, fuer den gekauft wird, kommt als Parameter mit.
        // Ohne ihn sucht der Checkout sich irgendeinen Workspace des Benutzers
        // und legt notfalls einen neuen an -- das Guthaben landete dann auf
        // einem Workspace, den der Kaeufer nie zu sehen bekommt. Ob der
        // Benutzer zu diesem Workspace gehoert, prueft spaeter der
        // CheckoutService; eine fremde Kennung faellt dort auf den bisherigen
        // Weg zurueck.
        $tenantUuid = request()->query('tenant');

        if (is_string($tenantUuid) && $tenantUuid !== '') {
            $cartDto->tenantUuid = $tenantUuid;
        }

        $product = $this->productService->getProductWithPriceBySlug($productSlug);

        if ($product === null) {
            abort(404);
        }

        if (! $product->is_active) {
            abort(404);
        }

        if ($quantity < 1) {
            $quantity = 1;
        }

        if ($product->max_quantity != 0 && $quantity > $product->max_quantity) {
            $quantity = $product->max_quantity;
        }

        // if product is already in cart, increase quantity
        foreach ($cartDto->items as $item) {
            if ($item->productId == $product->id) {
                $item->quantity += $quantity;
                $item->quantity = min($item->quantity, $product->max_quantity);
                $this->sessionService->saveCartDto($cartDto);

                return redirect()->route('checkout.product');
            }
        }

        $cartItem = new CartItemDto;
        $cartItem->productId = $product->id;
        $cartItem->quantity = $quantity;

        $cartDto->items[] = $cartItem;

        $this->sessionService->saveCartDto($cartDto);

        return redirect()->route('checkout.product');
    }

    /**
     * Erfolgsseite des Guthabenkaufs.
     *
     * Die Seite ist idempotent: Sie zeigt den Zustand einer Bestellung und
     * loest nichts aus. Deshalb wandert die Bestellung nach dem einmaligen
     * Einloesen des Gutscheins in die Adresszeile -- ein Neuladen oder der
     * Zurueck-Knopf fuehrt dann auf dieselbe Ansicht statt auf die Startseite.
     */
    public function productCheckoutSuccess(CreditLedgerService $creditLedger)
    {
        $orderUuid = request()->query('bestellung');

        if (! is_string($orderUuid) || $orderUuid === '') {
            $cartDto = $this->sessionService->getCartDto();

            if ($cartDto->orderId === null) {
                return view('checkout.product-thank-you', ['order' => null]);
            }

            if ($cartDto->discountCode !== null) {
                $this->discountService->redeemCodeForOrder($cartDto->discountCode, auth()->user(), $cartDto->orderId);
            }

            $order = Order::query()->whereKey($cartDto->orderId)->first();

            $this->sessionService->clearCartDto();

            if (! $order instanceof Order) {
                return view('checkout.product-thank-you', ['order' => null]);
            }

            return redirect()->route('checkout.product.success', ['bestellung' => $order->uuid]);
        }

        // Nur eigene Bestellungen: Eine fremde Kennung ist hier nicht
        // "verboten", sondern schlicht nicht vorhanden.
        $order = Order::query()
            ->where('uuid', $orderUuid)
            ->where('user_id', auth()->id())
            ->first();

        if (! $order instanceof Order) {
            return view('checkout.product-thank-you', ['order' => null]);
        }

        $tenant = $order->tenant_id === null ? null : Tenant::query()->withoutGlobalScopes()->find($order->tenant_id);

        return view('checkout.product-thank-you', [
            'order' => $order,
            'tenant' => $tenant,
            'credits' => $this->creditsIn($order),
            'balance' => $tenant instanceof Tenant ? $creditLedger->balanceFor($tenant) : 0,
            'isPending' => $order->status !== OrderStatus::SUCCESS,
        ]);
    }

    /**
     * Wie viel Guthaben diese Bestellung enthaelt. Gelesen wird dasselbe
     * Metadatenfeld, aus dem der Listener bucht -- eine zweite Zaehlweise waere
     * genau die Stelle, an der Anzeige und Buchung auseinanderlaufen.
     */
    private function creditsIn(Order $order): int
    {
        $items = OrderItem::query()->where('order_id', $order->getKey())->get();

        $creditsPerProduct = OneTimeProduct::query()
            ->whereIn('id', $items->pluck('one_time_product_id')->all())
            ->get()
            ->mapWithKeys(static fn (OneTimeProduct $product): array => [
                (int) $product->getKey() => (int) (($product->metadata ?? [])[CreditLedgerService::PRODUCT_METADATA_KEY] ?? 0),
            ])
            ->all();

        $credits = 0;

        foreach ($items as $item) {
            $credits += ($creditsPerProduct[(int) $item->one_time_product_id] ?? 0) * (int) $item->quantity;
        }

        return $credits;
    }
}
