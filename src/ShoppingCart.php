<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Support\Facades\DB;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
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
use MobileNowGroup\ShoppingCart\Traits\CartCoupons;
use MobileNowGroup\ShoppingCart\Traits\CartTools;

class ShoppingCart
{
    use CartCoupons, CartTools;

    protected CartStorageInterface $storage;

    /**
     * Cart constructor.
     */
    public function __construct(CartStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Returns cart.
     *
     * @param bool $isCheckout
     * @return ?CartModel
     */
    public function getCart(bool $isCheckout = false): ?CartModel
    {
        event(new CartGetBefore($isCheckout));

        $customerId = auth()->id();
        if (!$customerId) {
            return null;
        }

        $cart = $this->storage->getCart($customerId, $isCheckout);

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
            throw new CartServerException(ErrorConst::ERROR_CART_NOT_CREATED->value);
        }

        // 使用存储层添加商品
        $this->addItemToCart($cart, $item, $quantity);

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
        DB::transaction(function () use ($cart) {
            $cart->sub_total = $cart->base_sub_total = 0;
            $cart->discount = $cart->base_discount = 0;

            foreach ($cart->items as $item) {
                if (($cart->is_temp === false && $item->selected === true) || $cart->is_temp === true) {

                    $cart->discount += $item->discount_amount;
                    $cart->base_discount += $item->base_discount_amount;

                    $item->total = $item->latest_price * $item->quantity;
                    $item->base_total = $item->latest_price * $item->quantity;
                    $item->save();

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
        });

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

        if (! $this->getCart()) {
            return false;
        }

        $result = $this->storage->removeCartItem($itemId);

        if ($result) {
            event(new CartRemoveItemAfter($itemId));
        }

        return $result;
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
        try {
            $cartData = [
                'items_count' => 0,
                'customer_id' => auth()->id(),
                'customer_name' => auth()->user()?->name ?? '',
            ] + $data;

            $cart = $this->storage->createCart($cartData);
        } catch (\Exception $e) {
            throw new CartServerException(ErrorConst::ERROR_CREATE_CART_INSTANCE->value);
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

        $item = $this->storage->getCartItem($itemId);

        if (!$item) {
            throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
        }

        if ($quantity <= 0) {
            throw new CartClientException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE->value);
        }

        $updateData = [
            'quantity' => $quantity,
            'total' => $item->latest_price * $quantity,
            'base_total' => $item->latest_price * $quantity,
            'total_weight' => $item->weight * $quantity,
            'base_total_weight' => $item->weight * $quantity,
        ];

        $this->storage->updateCartItem($itemId, $updateData);

        // 重新获取更新后的项目用于事件
        $updatedItem = $this->storage->getCartItem($itemId);
        event(new CartUpdateItemAfter($updatedItem));

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

        if ($cart) {
            $this->storage->emptyCart($cart->id);
        }

        event(new CartEmptyAfter($cart));

        return $this->getCart();
    }

    /**
     * @param string $type
     * @param bool $selected
     * @param int|null $itemId
     * @return CartModel|null
     *
     * @throws CartClientException
     * @throws \Throwable
     */
    public function toggleBuy(string $type, bool $selected, ?int $itemId)
    {
        event(new CartToggleBuyBefore(type: $type, selected: $selected, itemId: $itemId));

        if (!in_array($type, ['single', 'all'])) {
            throw new CartClientException(ErrorConst::CODE_ERROR->value);
        }
        if (! $cart = $this->getCart()) {
            throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST->value);
        }

        if ($type === 'single') {
            if (!$itemId) {
                throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
            }

            $item = $this->storage->getCartItem($itemId);
            if (! $item) {
                throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST);
            }

            $this->storage->updateCartItem($itemId, ['selected' => $selected]);
        } else {
            $this->storage->toggleAllItems($cart->id, $selected);
        }

        event(new CartToggleBuyAfter(type: $type, selected: $selected, itemId: $itemId, cart: $cart));

        return $this->getCart();
    }

    /**
     * 类别量
     *
     * @return int
     */
    public function itemsCount(): int
    {
        if (! $cart = $this->getCart()) {
            return 0;
        }

        $stats = $this->storage->getCartStats($cart->id);

        return $stats['items_count'] ?? 0;
    }

    /**
     * 总数量
     *
     * @return int
     */
    public function itemsQty(): int
    {
        if (! $cart = $this->getCart()) {
            return 0;
        }

        $stats = $this->storage->getCartStats($cart->id);

        return $stats['items_qty'] ?? 0;
    }

    /**
     * 添加商品到购物车的内部方法
     *
     * @param CartModel $cart
     * @param Cartable $item
     * @param int $quantity
     * @return void
     */
    protected function addItemToCart(CartModel $cart, Cartable $item, int $quantity): void
    {
        // 检查是否已存在相同商品
        $existingItem = null;
        foreach ($cart->items as $cartItem) {
            if ($cartItem->itemable_id == $item->getKey() && $cartItem->itemable_type == get_class($item)) {
                $existingItem = $cartItem;
                break;
            }
        }

        if ($existingItem) {
            // 更新现有商品数量
            $newQuantity = $existingItem->quantity + $quantity;
            $updateData = [
                'quantity' => $newQuantity,
                'total' => $existingItem->latest_price * $newQuantity,
                'base_total' => $existingItem->latest_price * $newQuantity,
                'total_weight' => $existingItem->weight * $newQuantity,
                'base_total_weight' => $existingItem->weight * $newQuantity,
            ];
            $this->storage->updateCartItem($existingItem->id, $updateData);
        } else {
            // 添加新商品
            $itemData = [
                'itemable_id' => $item->getKey(),
                'itemable_type' => get_class($item),
                'quantity' => $quantity,
                'price' => $item->getPrice(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'base_price' => $item->getPrice(),
                'total' => $item->getPrice() * $quantity,
                'base_total' => $item->getPrice() * $quantity,
                'weight' => $item->getWeight() ?? 0,
                'selected' => true,
                'total_weight' => ($item->getWeight() ?? 0) * $quantity,
                'base_total_weight' => ($item->getWeight() ?? 0) * $quantity,
            ];
            $this->storage->addCartItem($cart->id, $itemData);
        }
    }
}
