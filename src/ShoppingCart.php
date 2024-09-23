<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
use MobileNowGroup\ShoppingCart\Exceptions\CartException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Traits\CartCoupons;
use MobileNowGroup\ShoppingCart\Traits\CartTools;

class ShoppingCart
{
    use CartCoupons,CartTools;

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
     * @param bool $isCheckout
     * @return Builder|Model|null
     */
    public function getCart(bool $isCheckout = false): Builder|Model|null
    {
        CartGetBefore::dispatch($isCheckout);

        $cart = CartModel::withWhereHas('items', function ($query) use ($isCheckout) {
            if ($isCheckout) {
                $query->where('selected', true);
            }
        })->firstWhere([
            'customer_id' => auth()->id(),
            'is_active' => true,
        ]);
        CartGetAfter::dispatch($cart);

        return $cart;
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
            if ($item->product->isCartItemInactive()) {
                ShoppingCartItem::destroy($item->id);
            }
        }

        $cart->save();

        return $cart;
    }

    /**
     * Add items in a cart with some cart and item details.
     *
     * @param Model $item
     * @param int $quantity
     * @param bool $isBuyNow
     * @return Builder|Model|null
     *
     * @throws CartException
     */
    public function addProduct(Model $item, int $quantity, bool $isBuyNow = false): Builder|Model|null
    {
        CartAddProductBefore::dispatch($item, $quantity);
        $cart = $this->getCart();

        if (! $item instanceof Cartable) {
            throw new CartException(ErrorConst::ERROR_NOT_INSTANCE_CARTABLE);
        }

        if (! $cart && ! $cart = $this->create(['is_temp' => $isBuyNow])) {
            throw new CartException(ErrorConst::ERROR_CART_NOT_CREATED);
        }
        $item->quantity = $quantity;
        $cart->storeItem($item);

        CartAddProductAfter::dispatch($cart);

        $this->collectTotals();

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
        CartCollectTotalBefore::dispatch($cart);

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

        CartCollectTotalAfter::dispatch($cart);
    }

    /**
     * Round cart totals.
     *
     * @param CartModel|Model $cart
     * @return CartModel
     */
    private function finalizeCartTotals(CartModel|Model $cart): CartModel
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
        CartRemoveItemBefore::dispatch($itemId);
        if (! $cart = $this->getCart()) {
            return false;
        }

        if ($cartItem = ShoppingCartItem::find($itemId)) {
            $cartItem->delete();

            CartRemoveItemAfter::dispatch($itemId);

            $this->collectTotals();

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
     * @throws CartException
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
            throw new CartException(ErrorConst::ERROR_CREATE_CART_INSTANCE);
        }

        return $cart;
    }

    /**
     * Update cart items information.
     *
     * @param int $itemId
     * @param int $quantity
     * @return bool
     *
     * @throws CartException
     */
    public function updateItems(int $itemId, int $quantity): bool
    {
        CartUpdateItemBefore::dispatch($itemId, $quantity);

        $item = ShoppingCartItem::find($itemId);

        if (! $item) {
            throw new CartException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
        }

        if ($quantity <= 0) {
            throw new CartException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE);
        }

        $item->quantity = $quantity;
        $item->total = $item->price * $quantity;
        $item->base_total = $item->price * $quantity;
        $item->total_weight = $item->weight * $quantity;
        $item->base_total_weight = $item->weight * $quantity;
        $item->save();

        CartUpdateItemAfter::dispatch($item);

        $this->collectTotals();

        return true;
    }

    /**
     * Remove every item from the cart
     */
    public function emptyCart(): static
    {
        CartEmptyBefore::dispatch();
        if ($cart = $this->getCart()) {
            $cart->items()->delete();
        }
        CartEmptyAfter::dispatch();

        return $this;
    }

    /**
     * @param string $type
     * @param bool $selected
     * @param int|null $itemId
     * @return void
     *
     * @throws CartException
     * @throws \Throwable
     */
    public function toggleBuy(string $type, bool $selected, ?int $itemId)
    {
        CartToggleBuyBefore::dispatch($type, $selected, $itemId);
        if (!in_array($type, ['single', 'all'])) {
            throw new CartException(ErrorConst::CODE_ERROR);
        }
        if (! $cart = $this->getCart()) {
            throw new CartException(ErrorConst::ERROR_CART_NOT_EXIST);
        }

        if ($type === 'single') {
            $item = ShoppingCartItem::find($itemId);

            if (! $item) {
                throw new CartException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
            }
            $item->selected = $selected;
            $item->save();

            return;
        }

        DB::transaction(function () use ($selected, $cart) {
            ShoppingCartItem::where('cart_id', $cart->id)->update(['selected' => $selected]);
        });

        CartToggleBuyAfter::dispatch();

        $this->collectTotals();
    }
}
