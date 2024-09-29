<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Support\Facades\DB;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartAddProductAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartAddProductBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartCollectTotalAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartCollectTotalBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartEmptyAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartEmptyBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartGetAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartGetBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartRemoveItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartRemoveItemBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartToggleBuyAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartToggleBuyBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartUpdateItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartUpdateItemBefore;
use MobileNowGroup\ShoppingCart\Exceptions\CartClientException;
use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Traits\CartCoupons;
use MobileNowGroup\ShoppingCart\Traits\CartTools;

class ShoppingCart
{
    use CartCoupons,CartTools;

    /**
     * Cart constructor.
     */
    public function __construct() {}

    /**
     * Returns cart.
     *
     * @param bool $isCheckout
     * @return ?CartModel
     */
    public function getCart(bool $isCheckout = false): ?CartModel
    {
        event(new CartGetBefore($isCheckout));

        $cart = CartModel::withWhereHas('items', function ($query) use ($isCheckout) {
            if ($isCheckout) {
                $query->where('selected', true);
            }
        })->firstWhere([
            'customer_id' => auth()->id(),
            'is_active' => true,
        ]);

        event(new CartGetAfter($cart));

        return $cart;
    }

    /**
     * Add items in a cart with some cart and item details.
     *
     * @param Cartable $item
     * @param int $quantity
     * @param bool $isBuyNow
     * @return CartModel
     *
     * @throws CartServerException|CartClientException
     */
    public function addProduct(Cartable $item, int $quantity, bool $isBuyNow = false): CartModel
    {
        event(new CartAddProductBefore(item: $item, quantity: $quantity, isBuyNow: $isBuyNow));

        $cart = $this->getCart();

        if (! $cart && ! $cart = $this->create(['is_temp' => $isBuyNow])) {
            throw new CartServerException(ErrorConst::ERROR_CART_NOT_CREATED);
        }
        $cart->storeItem($item, $quantity);

        event(new CartAddProductAfter(item: $item, cart: $cart));

        return $this->getCart();
    }

    /**
     * Updates cart totals.
     *
     * @return void
     */
    public function collectTotals(): void
    {

        if (! $cart = $this->getCart()) {
            return;
        }
        event(new CartCollectTotalBefore($cart));

        $cart->grand_total = $cart->base_grand_total = 0;
        $cart->sub_total = $cart->base_sub_total = 0;
        $cart->discount = $cart->base_discount = 0;

        foreach ($cart->items as $item) {
            if (((bool) $cart->is_temp === false && (bool) $item->selected === true) || (bool) $cart->is_temp === true) {
                $cart->discount += $item->discount_amount;
                $cart->base_discount += $item->base_discount_amount;

                $cart->sub_total = (float) $cart->sub_total + $item->total;
                $cart->base_sub_total = (float) $cart->base_sub_total + $item->base_total;
            }
        }

        $cart->grand_total = $cart->sub_total - $cart->discount;
        $cart->base_grand_total = $cart->base_sub_total - $cart->base_discount;

        $cart = $this->finalizeCartTotals($cart);

        $cart->items_count = $cart->items->count();

        $cart->items_qty = $cart->items->sum('quantity');

        $cart->save();

        event(new CartCollectTotalAfter($cart));
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
     * Remove the item from the cart.
     *
     * @param int $itemId
     * @return bool
     */
    public function removeItem(int $itemId): bool
    {
        event(new CartRemoveItemBefore($itemId));
        if (! $cart = $this->getCart()) {
            return false;
        }

        if ($cartItem = ShoppingCartItem::find($itemId)) {
            $cartItem->delete();

            event(new CartRemoveItemAfter($itemId));

            return true;
        }

        return false;
    }

    /**
     * Create new cart instance.
     *
     * @param array|null $data
     * @return mixed
     *
     * @throws CartServerException
     */
    public function create(?array $data = []): mixed
    {
        $cartData = [
            'items_count' => 0,
            'customer_id' => auth()->id(),
            'customer_name' => auth()->user()->name ?? '',
        ] + $data;

        $cart = CartModel::create($cartData);

        if (! $cart) {
            throw new CartServerException(ErrorConst::ERROR_CREATE_CART_INSTANCE);
        }

        return $cart;
    }

    /**
     * Update cart items information.
     *
     * @param int $itemId
     * @param int $quantity
     * @return CartModel|null
     *
     * @throws CartClientException
     */
    public function updateItems(int $itemId, int $quantity): ?CartModel
    {
        event(new CartUpdateItemBefore($itemId, $quantity));

        $item = ShoppingCartItem::find($itemId);

        if (!$item) {
            throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
        }

        if ($quantity <= 0) {
            throw new CartClientException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE);
        }

        $item->quantity = $quantity;
        $item->total = $item->price * $quantity;
        $item->base_total = $item->price * $quantity;
        $item->total_weight = $item->weight * $quantity;
        $item->base_total_weight = $item->weight * $quantity;
        $item->save();

        event(new CartUpdateItemAfter($item));

        return $this->getCart();
    }

    /**
     * Remove every item from the cart
     *
     * @return CartModel|null
     */
    public function emptyCart(): ?CartModel
    {
        $cart = $this->getCart() ?? null;

        event(new CartEmptyBefore($cart));

        $cart?->items()->delete();

        event(new CartEmptyAfter($cart));

        return $this->getCart();
    }

    /**
     * @param string $type
     * @param bool $selected
     * @param int|null $itemId
     * @return void
     *
     * @throws CartClientException
     * @throws \Throwable
     */
    public function toggleBuy(string $type, bool $selected, ?int $itemId)
    {
        event(new CartToggleBuyBefore(type: $type, selected: $selected, itemId: $itemId));

        if (!in_array($type, ['single', 'all'])) {
            throw new CartClientException(ErrorConst::CODE_ERROR);
        }
        if (! $cart = $this->getCart()) {
            throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST);
        }

        if ($type === 'single') {
            $item = ShoppingCartItem::find($itemId);

            if (! $item) {
                throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
            }
            $item->selected = $selected;
            $item->save();

            return;
        }

        DB::transaction(function () use ($selected, $cart) {
            ShoppingCartItem::where('cart_id', $cart->id)->update(['selected' => $selected]);
        });

        event(new CartToggleBuyAfter(type: $type, selected: $selected, itemId: $itemId, cart: $cart));

    }
}
