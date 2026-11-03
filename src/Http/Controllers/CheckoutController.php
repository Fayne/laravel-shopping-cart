<?php

namespace Fayne\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Facades\ShoppingCart;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Events\CheckoutValidating;
use Illuminate\Database\Eloquent\Collection;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

class CheckoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ShoppingCart::transaction(function () {
            // Read after acquiring the same customer lock used by cart mutations.
            $cart = CartModel::where('customer_id', auth()->id())
                ->where('is_active', true)->lockForUpdate()->first();
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json(['message' => 'Cart is empty'], 400);
            }
            if ($cart->coupon_code) {
                ShoppingCart::setCouponCode($cart->coupon_code);
                $cart->refresh();
            }
            /** @var Collection<int, ShoppingCartItem> $items */
            $items = $cart->items()->where('selected', true)->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return response()->json(['message' => 'No items selected for checkout'], 400);
            }
            $subtotal = $discount = 0;
            $snapshots = [];
            $skuClass = config('shopping-cart.models.skus.model');
            foreach ($items as $item) {
                $sku = $item->itemable;
                abort_unless($sku instanceof Cartable && $sku instanceof $skuClass && $sku->canBePurchased(), 422, 'Item cannot be purchased');
                abort_unless($item->quantity > 0, 422, 'Invalid quantity');
                abort_unless(is_finite($sku->getPrice()) && $sku->getPrice() >= 0, 422, 'Invalid price');
                $price = (int) round($sku->getPrice() * 100);
                $lineDiscount = (int) round($item->discount_amount * 100);
                $lineTotal = $price * $item->quantity;
                abort_unless($price >= 0 && $lineDiscount >= 0 && $lineDiscount <= $lineTotal, 422, 'Invalid amount');
                $validator = config('shopping-cart.checkout.validator');
                if (is_string($validator) && class_exists($validator)) {
                    $validator = app($validator);
                }
                abort_unless(is_callable($validator), 503, 'Checkout stock validator is not configured');
                abort_unless($validator($sku, $item->quantity, $cart), 422, 'Insufficient stock');
                // Synchronous listeners may reserve stock in this same transaction.
                event(new CheckoutValidating($cart, $item, $sku));
                $subtotal += $lineTotal;
                $discount += $lineDiscount;
                $snapshots[] = [
                    'product_id' => $sku->getAttribute('product_id'),
                    'sku_id' => $sku->getKey(),
                    'name_snapshot' => $sku->getName(),
                    'price_snapshot' => $price / 100,
                    'quantity' => $item->quantity,
                    'discount_amount' => $lineDiscount / 100,
                ];
            }
            abort_if($subtotal > 9999999999 || $discount > $subtotal, 422, 'Amount exceeds supported range');
            $checkout = Checkout::create([
                'user_id' => auth()->id(), 'status' => 0,
                'currency' => config('shopping-cart.checkout.currency', 'CNY'),
                'subtotal_amount' => $subtotal / 100, 'discount_amount' => $discount / 100,
                'tax_amount' => 0, 'shipping_amount' => 0,
                'total_amount' => ($subtotal - $discount) / 100,
                'metadata' => $cart->metadata,
                'expires_at' => now()->addMinutes(config('shopping-cart.checkout.expiration', 30)),
            ]);
            $checkout->items()->createMany($snapshots);
            foreach ($items as $item) {
                abort_unless(app(CartStorageInterface::class)->removeCartItem($item->id), 409, 'Cart changed');
            }
            ShoppingCart::collectTotals();
            if ($cart->is_temp) {
                ShoppingCart::deActivateCart();
                ShoppingCart::activateCartIfCacheHasDeactivatedCartId();
            }

            return response()->json(['code' => 0, 'message' => '订单创建成功', 'data' => [
                'checkout_id' => $checkout->id, 'total_amount' => $checkout->total_amount,
                'expires_at' => $checkout->expires_at,
            ]]);
        });
    }
}
