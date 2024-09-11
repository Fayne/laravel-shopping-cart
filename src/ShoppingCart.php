<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use MobileNowGroup\ShoppingCart\Exceptions\CartException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Traits\CartCoupons;
use MobileNowGroup\ShoppingCart\Models\ProductSku;

class ShoppingCart
{
    use CartCoupons;

    //const DEFAULT_INSTANCE = 'shopping';

    /**
     * Instance of the session manager.
     *
     * @var CartModel
     */
    private CartModel $cart;

    /**
     * Instance of the event dispatcher.
     *
     * @var Dispatcher
     */
    private Dispatcher $events;

    /**
     * Defines the discount percentage.
     *
     * @var float
     */
    private float $discount = 0;

    /**
     * Cart constructor.
     */
    public function __construct() {}

    /**
     * Returns cart.
     *
     * @return CartModel|null
     */
    public function getCart(): ?CartModel
    {
        $cart = CartModel::firstWhere([
            'customer_id' => auth()->id(),
            'is_active' => 1,
        ]);

        return $this->removeInactiveItems($cart);
    }

    /**
     * Remove cart items, whose product is inactive.
     *
     * @param CartModel|null $cart
     * @return CartModel|null
     */
    public function removeInactiveItems(?CartModel $cart = null): ?CartModel
    {
        if (! $cart) {
            return $cart;
        }

        foreach ($cart->items as $item) {
            if ($this->isCartItemInactive($item)) {

                ShoppingCartItem::destroy($item->id);

                if ($cart->items->count() == 0) {
                    CartModel::destroy($cart->id);
                }
            }
        }

        $cart->save();

        return $cart;
    }

    /**
     * Returns true, if cart item is inactive.
     *
     * @param ShoppingCartItem $item
     * @return bool
     */
    private function isCartItemInactive(ShoppingCartItem $item): bool
    {
        static $loadedCartItem = [];

        if (array_key_exists($item->product_id, $loadedCartItem)) {
            return $loadedCartItem[$item->product_id];
        }

        return $loadedCartItem[$item->product_id] = $item->product->status == 0;
    }

    /**
     * Add items in a cart with some cart and item details.
     *
     * @param string $productSkuId
     * @param array|null $options
     * @return ShoppingCart|string|array
     *
     * @throws CartException
     */
    public function add(string $productSkuId, int $qty, ?array $options = []): array|string|CartModel
    {
        Event::dispatch('checkout.cart.add.before', $productSkuId);

        $cart = $this->getCart();

        if (! $cart && ! $cart = $this->create($options)) {
            throw new CartException(ErrorConst::ERROR_CART_NOT_CREATED);
        }
        $productSku = ProductSku::find($productSkuId)->load('product');

        if ($productSku->product->status === 0) {
            throw new CartException(ErrorConst::ERROR_ADD_INACTIVE_PRODUCT);
        }

        $cartProducts = $productSku->prepareForCart($qty, $options);

        foreach ($cartProducts as $cartProduct) {
            $cartItem = $this->getItemByProduct();

            if ($cartItem->isEmpty()) {
                ShoppingCartItem::create(array_merge($cartProduct, ['cart_id' => $cart->id]));
            } else {
                ShoppingCartItem::where('id', $cartItem->id)->update($cartProduct, $cartItem->id);
            }

        }

        Event::dispatch('checkout.cart.add.after', $cart);

        $this->collectTotals();

        return $this->getCart();
    }

    /**
     * Get cart item by product.
     *
     * @return mixed
     */
    public function getItemByProduct()
    {
        return $this->getCart()->items;
    }

    /**
     * Updates cart totals.
     *
     * @return void
     */
    public function collectTotals(): void
    {
        if (! $this->validateItems()) {
            return;
        }

        if (! $cart = $this->getCart()) {
            return;
        }

        Event::dispatch('checkout.cart.collect.totals.before', $cart);

        $cart->grand_total = $cart->base_grand_total = 0;
        $cart->sub_total = $cart->base_sub_total = 0;
        // $cart->tax_total = $cart->base_tax_total = 0;
        $cart->discount = $cart->base_discount = 0;

        foreach ($cart->items as $item) {
            $cart->discount += $item->discount_amount;
            $cart->base_discount += $item->base_discount_amount;

            $cart->sub_total = (float) $cart->sub_total + $item->total;
            $cart->base_sub_total = (float) $cart->base_sub_total + $item->base_total;
        }

        $cart->grand_total = $cart->sub_total - $cart->discount;
        $cart->base_grand_total = $cart->base_sub_total - $cart->base_discount;

        //@todo 暂不考虑快递费
        //        if ($shipping = $cart->selected_shipping_rate) {
        //            $cart->grand_total = (float) $cart->grand_total + $shipping->price - $shipping->discount_amount;
        //            $cart->base_grand_total = (float) $cart->base_grand_total + $shipping->base_price - $shipping->base_discount_amount;
        //
        //            $cart->discount_amount += $shipping->discount_amount;
        //            $cart->base_discount_amount += $shipping->base_discount_amount;
        //        }

        $cart = $this->finalizeCartTotals($cart);

        $quantities = 0;

        foreach ($cart->items as $item) {
            $quantities = $quantities + $item->quantity;
        }

        $cart->items_count = $cart->items->count();

        $cart->items_qty = $quantities;

        $cart->save();

        Event::dispatch('checkout.cart.collect.totals.after', $cart);
    }

    /**
     * Round cart totals.
     *
     * @param CartModel $cart
     * @return CartModel
     */
    private function finalizeCartTotals(CartModel $cart): CartModel
    {
        $cart->discount = round($cart->discount, 2);
        $cart->base_discount = round($cart->base_discount, 2);

        $cart->sub_total = round($cart->sub_total, 2);
        $cart->base_sub_total = round($cart->base_sub_total, 2);

        $cart->grand_total = round($cart->grand_total, 2);
        $cart->base_grand_total = round($cart->base_grand_total, 2);

        return $cart;
    }

    /**
     * To validate if the product information is changed by admin and the items have been added to the cart before it.
     *
     * @return bool
     */
    public function validateItems(): bool
    {
        if (! $cart = $this->getCart()) {
            return false;
        }

        if (count($cart->items) === 0) {
            CartModel::destroy($cart->id);

            return false;
        }

        $isInvalid = false;

        foreach ($cart->items as $item) {
            if ($item->product->status === 0) {
                $this->removeItem($item->id);

                $isInvalid = true;

            }

            $price = ! is_null($item->custom_price) ? $item->custom_price : $item->base_price;

            ShoppingCartItem::where('id', $item->id)->update([
                'price' => $price,
                'base_price' => $price,
                'total' => $price * $item->quantity,
                'base_total' => $price * $item->quantity,
            ]);

        }

        return ! $isInvalid;
    }

    /**
     * Remove the item from the cart.
     *
     * @param int $itemId
     * @return bool
     */
    public function removeItem(int $itemId): bool
    {
        Event::dispatch('checkout.cart.delete.before', $itemId);

        if (! $cart = $this->getCart()) {
            return false;
        }

        if ($cartItem = ShoppingCartItem::find($itemId)) {
            $cartItem->delete();

            if ($cart->items->count() == 0) {
                CartModel::destroy($cart->id);
            }

            Event::dispatch('checkout.cart.delete.after', $itemId);

            $this->collectTotals();

            return true;
        }

        return false;
    }

    /**
     * Create new cart instance.
     *
     * @param $data
     * @return mixed
     *
     * @throws CartException
     */
    public function create(?array $data = []): mixed
    {
        $cartData = [
            'items_count' => 1,
            'customer_id' => auth()->id(),
            // 'is_guest' => 0,
            'customer_first_name' => auth()->user()->name ?? '',
            'customer_last_name' => auth()->user()->name ?? '',
            'customer_email' => auth()->user()->email ?? '',
        ] + $data;

        $cart = CartModel::create($cartData);

        if (! $cart) {

            throw new CartException(ErrorConst::ERROR_CREATE_CART_INSTANCE);
        }

        return $cart;
    }

    /**
     * Get the Formatted number.
     *
     *
     * @param float $value
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    private function numberFormat(float $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator): string
    {
        if (is_null($decimals)) {
            $decimals = config('shopping-cart.format.decimals', 2);
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = config('shopping-cart.format.decimal_point', '.');
        }

        if (is_null($thousandSeparator)) {
            $thousandSeparator = config('cart.format.thousand_separator', ',');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Update cart items information.
     *
     * @param int $cartItemId
     * @param int $qty
     * @return bool
     *
     * @throws CartException
     */
    public function updateItems(int $itemId, int $quantity): bool
    {
        $item = ShoppingCartItem::find($itemId)->load('productSku');

        if (! $item) {
            throw new CartException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
        }

        if ($item->product && $item->product->status === 0) {
            throw new CartException(ErrorConst::ERROR_PRODUCT_NOT_AVAILABLE);
        }

        if ($quantity <= 0) {
            //$this->removeItem($itemId);
            throw new CartException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE);
        }

        $item->quantity = $quantity;

        if ($quantity < $item->productSku->stock) {
            throw new CartException(ErrorConst::ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE);
        }

        Event::dispatch('checkout.cart.update.before', $item);

        ShoppingCartItem::where('id', $itemId)->update([
            'quantity' => $quantity,
            'total' => $item->price * $quantity,
            'base_total' => $item->price * $quantity,
            'total_weight' => $item->weight * $quantity,
            'base_total_weight' => $item->weight * $quantity,
        ], $itemId);

        Event::dispatch('checkout.cart.update.after', $item);

        $this->collectTotals();

        return true;
    }
}
