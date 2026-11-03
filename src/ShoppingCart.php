<?php

namespace Fayne\ShoppingCart;

use Illuminate\Support\Facades\DB;
use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Events\CartPackage\CartAddProductAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartAddProductBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartCollectTotalAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartCollectTotalBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartEmptyAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartEmptyBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartGetAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartGetBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartRemoveItemAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartRemoveItemBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartSetSelectionAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartSetSelectionBefore;
use Fayne\ShoppingCart\Events\CartPackage\CartUpdateItemAfter;
use Fayne\ShoppingCart\Events\CartPackage\CartUpdateItemBefore;
use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\CartServerException;
use Fayne\ShoppingCart\Exceptions\ErrorConst;
use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;
use Fayne\ShoppingCart\Traits\CartCoupons;
use Fayne\ShoppingCart\Traits\CartTools;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

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
        return $this->transaction(function () use ($item, $quantity, $isBuyNow) {
            if ($quantity < 1 || !is_finite($item->getPrice()) || $item->getPrice() < 0 || !$item->canBePurchased()) {
                throw new CartClientException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE->value, httpCode: 422);
            }
            event(new CartAddProductBefore(item: $item, quantity: $quantity, isBuyNow: $isBuyNow));

            $cart = $this->getCart();

            if (! $cart && ! $cart = $this->create(['is_temp' => $isBuyNow])) {
                throw new CartServerException(ErrorConst::ERROR_CART_NOT_CREATED->value);
            }

            // 使用存储层添加商品
            $this->addItemToCart($cart, $item, $quantity);

            $this->collectTotals();
            event(new CartAddProductAfter(item: $item, cart: $cart));

            return $this->getCart();

        });
    }

    /**
     * Updates cart totals.
     *
     * @return void
     */
    public function collectTotals(): void
    {
        $this->transaction(function () {

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

                        $item->total = round($item->latest_price * 100) * $item->quantity / 100;
                        $item->base_total = $item->total;
                        if ($item->discount_amount < 0 || $item->discount_amount > $item->total) {
                            throw new CartClientException(ErrorConst::ERROR_COUPON_INVALID->value, httpCode: 422);
                        }
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

                if (!$this->storage->updateCart($cart)) {
                    throw new CartServerException(ErrorConst::ERROR_CREATE_CART_INSTANCE->value);
                }
            });

            event(new CartCollectTotalAfter($cart));

        });
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
     * @param int|string $itemId
     * @return bool
     */
    public function removeItem(int|string $itemId): bool
    {
        return $this->transaction(function () use ($itemId) {
            event(new CartRemoveItemBefore($itemId));

            if (! $this->getCart()) {
                return false;
            }

            $this->ownedItem($itemId);
            $result = $this->storage->removeCartItem($itemId);

            if ($result) {
                $this->collectTotals();
                event(new CartRemoveItemAfter($itemId));
            }

            return $result;

        });
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
        return $this->transaction(function () use ($data) {
            try {
                if ($existing = $this->getCart()) {
                    return $existing;
                }
                $cartData = [
                    'items_count' => 0,
                    'customer_id' => auth()->id(),
                    'customer_name' => auth()->user()->name ?? '',
                ] + $data;

                $cart = $this->storage->createCart($cartData);
            } catch (\Exception $e) {
                throw new CartServerException(ErrorConst::ERROR_CREATE_CART_INSTANCE->value);
            }

            return $cart;

        });
    }

    /**
     * @param int|string $itemId
     * @param bool $selected
     * @return CartModel
     *
     * @throws CartClientException
     * @throws \Throwable
     */
    public function setCartItemSelection(int|string $itemId, bool $selected = true): CartModel
    {
        return $this->transaction(function () use ($itemId, $selected) {
            event(new CartSetSelectionBefore(itemId: $itemId, selected: $selected));

            if (! $cart = $this->getCart()) {
                throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST->value);
            }

            if (!$itemId) {
                throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST->value);
            }

            $item = $this->ownedItem($itemId);

            abort_unless($this->storage->updateCartItem($itemId, ['selected' => $selected]), 409, 'Cart changed');

            $this->collectTotals();
            event(new CartSetSelectionAfter(itemId: $itemId, selected: $selected, cart: $cart));

            return $this->getCart();

        });
    }

    /**
     * @param bool $selected
     * @return CartModel
     */
    public function setAllCartItemsSelected(bool $selected = true): CartModel
    {
        return $this->transaction(function () use ($selected) {
            $cart = $this->getCart();
            if (!$cart) {
                throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST->value, httpCode: 404);
            }
            abort_unless($this->storage->toggleAllItems($cart->id, $selected), 409, 'Cart changed');
            $this->collectTotals();

            return $this->getCart();

        });
    }

    /**
     * Update cart items information.
     *
     * @param int|string $itemId
     * @param int $quantity
     * @return CartModel|null
     *
     * @throws CartClientException
     */
    public function updateItems(int|string $itemId, int $quantity): ?CartModel
    {
        return $this->transaction(function () use ($itemId, $quantity) {
            event(new CartUpdateItemBefore($itemId, $quantity));

            $item = $this->ownedItem($itemId);

            if ($quantity <= 0) {
                throw new CartClientException(ErrorConst::ERROR_QUANTITY_NOT_AVAILABLE->value, httpCode: 422);
            }

            $updateData = [
                'quantity' => $quantity,
                'total' => $item->latest_price * $quantity,
                'base_total' => $item->latest_price * $quantity,
                'total_weight' => $item->weight * $quantity,
                'base_total_weight' => $item->weight * $quantity,
            ];

            abort_unless($this->storage->updateCartItem($itemId, $updateData), 409, 'Cart changed');

            // 重新获取更新后的项目用于事件
            $updatedItem = $this->storage->getCartItem($itemId);
            $this->collectTotals();
            event(new CartUpdateItemAfter($updatedItem));

            return $this->getCart();

        });
    }

    /**
     * Remove every item from the cart
     *
     * @return CartModel|null
     */
    public function emptyCart(): ?CartModel
    {
        return $this->transaction(function () {
            $cart = $this->getCart() ?? null;

            event(new CartEmptyBefore($cart));

            if ($cart) {
                if ($cart->items->isNotEmpty()) {
                    abort_unless($this->storage->emptyCart($cart->id), 409, 'Cart changed');
                }
                $this->collectTotals();
            }

            event(new CartEmptyAfter($cart));

            return $this->getCart();

        });
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
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        abort_unless(auth()->id() !== null, 401);

        return DB::transaction(function () use ($callback) {
            $customer = DB::table(config('shopping-cart.models.users.table', 'users'))
                ->where('id', auth()->id())->lockForUpdate()->first();
            abort_unless($customer !== null, 401);

            return $callback();
        }, 3);
    }

    protected function ownedItem(int|string $itemId): ShoppingCartItem
    {
        $cart = $this->getCart();
        /** @var ShoppingCartItem|null $item */
        $item = $cart?->items()->whereKey($itemId)->first();
        if (!$item) {
            throw new CartClientException(ErrorConst::ERROR_PRODUCT_NOT_EXIST->value, httpCode: 404);
        }

        return $item;
    }

    protected function addItemToCart(CartModel $cart, Cartable $item, int $quantity): void
    {
        // 检查是否已存在相同商品
        $existingItem = null;
        foreach ($cart->items as $cartItem) {
            if ($cartItem->itemable_id == $item->getKey() && $cartItem->itemable_type == $item->getMorphClass()) {
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
            abort_unless($this->storage->updateCartItem($existingItem->id, $updateData), 409, 'Cart changed');
        } else {
            // 添加新商品
            $itemData = [
                'itemable_id' => $item->getKey(),
                'itemable_type' => $item->getMorphClass(),
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
