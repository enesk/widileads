<?php

namespace App\Http\Controllers;

use App\Dto\CartItemDto;
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

    public function productCheckoutSuccess()
    {
        $cartDto = $this->sessionService->getCartDto();

        if ($cartDto->orderId === null) {
            return redirect()->route('home');
        }

        if ($cartDto->discountCode !== null) {
            $this->discountService->redeemCodeForOrder($cartDto->discountCode, auth()->user(), $cartDto->orderId);
        }

        $this->sessionService->clearCartDto();

        return view('checkout.product-thank-you');
    }
}
