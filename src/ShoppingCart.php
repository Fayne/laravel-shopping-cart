<?php

namespace Fayne\ShoppingCart;

use Fayne\ShoppingCart\Support\CartSku;
use Fayne\ShoppingCart\Support\CartCurrency;
use Illuminate\Support\Facades\DB;
use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
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

        $customerId = $this->customerId();
        if ($customerId === null && !app()->bound(CartContextResolver::class)) {
            return null;
        }

        $cart = $this->storage->getCart((string) $customerId, $isCheckout);

        event(new CartGetAfter($cart));

        return $cart;
    }

    /**
     * @return list<array{cart_item_id: string, sku_id: int|string, quantity: int, selected: bool}>
     */
    public function selectedItemsForQuote(): array
    {
        $cart = $this->getCart(isCheckout: true);
        if ($cart === null) {
            return [];
        }

        $items = [];
        foreach ($cart->items as $item) {
            $sku = CartSku::query()->find($item->itemable_id);
            abort_unless($sku instanceof Cartable && $sku->getMorphClass() === $item->itemable_type, 422);
            $price = $this->validatedUnitPrice($sku);
            $quantity = (int) $item->quantity;
            abort_if($quantity < 1 || $price < 0 || $price > intdiv(PHP_INT_MAX, $quantity), 422);

            $items[] = [
                'cart_item_id' => (string) $item->getKey(),
                'sku_id' => $sku->getKey(),
                'quantity' => $quantity,
                'selected' => true,
            ];
        }

        return $items;
    }

    /**
     * Consume quoted lines in the same database transaction as the host order.
     *
     * @param list<array{cart_item_id: string, sku_id: int|string, quantity: int, selected: bool}> $quotedItems
     */
    public function consumeOrderItems(string $cartId, int|string $orderId, array $quotedItems): void
    {
        abort_unless(DB::transactionLevel() > 0, 409, 'An order transaction is required.');
        abort_if(trim((string) $orderId) === '' || $quotedItems === [], 422);

        $lines = array_column($quotedItems, null, 'cart_item_id');
        abort_unless(count($lines) === count($quotedItems), 422);
        ksort($lines);

        $this->transaction(function () use ($cartId, $orderId, $lines): void {
            $identity = $this->getCacheKeyForDeactivatedCartId();
            $key = hash('sha256', serialize([$identity, (string) $orderId]));
            $fingerprint = hash('sha256', serialize([$cartId, $lines]));
            $receipts = DB::table('shopping_cart_order_consumptions');

            if ($receipt = $receipts->where('id', $key)->lockForUpdate()->first()) {
                abort_unless($receipt->fingerprint === $fingerprint, 409);

                return;
            }

            $cart = CartModel::query()->without('items')
                ->where('customer_id', $this->customerId())
                ->where('is_active', true)
                ->lockForUpdate()->find($cartId);
            abort_if($cart === null, 404);
            $items = ShoppingCartItem::query()->where("cart_id", $cartId)->whereKey(array_keys($lines))->lockForUpdate()->get();
            abort_unless($items->count() === count($lines), 409);

            foreach ($items as $item) {
                $quoted = $lines[$item->getKey()];
                abort_unless($item->selected && $quoted['selected'] === true
                    && (string) $item->itemable_id === (string) $quoted['sku_id']
                    && (int) $item->quantity === $quoted['quantity'], 409);
            }

            $cart->items()->whereKey(array_keys($lines))->delete();
            $this->collectTotals();
            if ($cart->is_temp && !$cart->items()->exists()) {
                $this->deActivateCart();
                $this->activateCartIfCacheHasDeactivatedCartId();
            }

            DB::table('shopping_cart_order_consumptions')->insert([
                'id' => $key,
                'fingerprint' => $fingerprint,
            ]);
        });
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
            $skuClass = CartSku::model();
            abort_unless($item instanceof $skuClass, 422, 'Invalid SKU');
            $item = CartSku::query()->whereKey($item->getKey())->first();
            abort_unless($item instanceof Cartable, 422, 'SKU is unavailable');

            $price = $this->validatedUnitPrice($item);
            if ($quantity < 1 || $price > intdiv(PHP_INT_MAX, $quantity) || !$item->canBePurchased()) {
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
                $subtotal = $discount = $baseDiscount = 0;

                foreach ($cart->items as $item) {
                    if (($cart->is_temp === false && $item->selected === true) || $cart->is_temp === true) {

                        $lineTotal = $item->lineAmountInMinorUnits((int) $item->quantity);
                        $lineDiscount = (int) round($item->discount_amount * 100);
                        $lineBaseDiscount = (int) round($item->base_discount_amount * 100);
                        if ($lineDiscount < 0 || $lineDiscount > $lineTotal
                            || $lineBaseDiscount < 0 || $lineBaseDiscount > $lineTotal) {
                            throw new CartClientException(ErrorConst::ERROR_COUPON_INVALID->value, httpCode: 422);
                        }
                        abort_if($lineTotal > PHP_INT_MAX - $subtotal, 422, 'Amount exceeds supported range');

                        $item->total = $item->base_total = $lineTotal / 100;
                        $item->save();

                        $subtotal += $lineTotal;
                        $discount += $lineDiscount;
                        $baseDiscount += $lineBaseDiscount;
                    }
                }

                $cart->sub_total = $cart->base_sub_total = $subtotal / 100;
                $cart->discount = $discount / 100;
                $cart->base_discount = $baseDiscount / 100;
                $cart->grand_total = ($subtotal - $discount) / 100;
                $cart->base_grand_total = ($subtotal - $baseDiscount) / 100;

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
                    'customer_id' => $this->customerId(),
                    'customer_name' => app()->bound(CartContextResolver::class)
                        ? null : (auth()->user()->name ?? ''),
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
                'total' => $item->lineAmount($quantity),
                'base_total' => $item->lineAmount($quantity),
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
        if (app()->bound(CartContextResolver::class)) {
            $context = app(CartContextResolver::class);
            $customerId = $this->customerId();
            $sessionId = $customerId === null ? $context->sessionId() : null;
            abort_if($customerId === null && trim((string) $sessionId) === '', 401);
            $key = hash('sha256', serialize([
                (string) $context->tenantId(),
                $customerId === null ? 'guest' : 'customer',
                $customerId === null ? $sessionId : (string) $customerId,
            ]));

            return DB::transaction(function () use ($key, $callback) {
                DB::table('shopping_cart_guest_locks')->upsert(['id' => $key], ['id'], ['id']);

                return $callback();
            }, 3);
        }

        abort_unless($this->customerId() !== null, 401);

        return DB::transaction(function () use ($callback) {
            $customer = DB::table(config('shopping-cart.models.users.table', 'users'))
                ->where('id', $this->customerId())->lockForUpdate()->first();
            abort_unless($customer !== null, 401);

            return $callback();
        }, 3);
    }

    protected function customerId(): int|string|null
    {
        if (app()->bound(CartContextResolver::class)) {
            $context = app(CartContextResolver::class);
            abort_if(trim((string) $context->tenantId()) === '', 503);

            return $context->customerId();
        }

        abort_if(config('shopping-cart.tenancy.enabled', false), 503);

        return auth()->id();
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

    private function validatedUnitPrice(Cartable $item): int
    {
        $price = $item->getPriceInMinorUnits();
        abort_if($price < 0 || $item->getCurrency() !== CartCurrency::current(), 422, 'Invalid SKU price or currency');

        return $price;
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
                'total' => $existingItem->lineAmount($newQuantity),
                'base_total' => $existingItem->lineAmount($newQuantity),
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
                'price' => ($item->getPriceInMinorUnits() / 100),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'base_price' => ($item->getPriceInMinorUnits() / 100),
                'total' => ($item->getPriceInMinorUnits() * $quantity) / 100,
                'base_total' => ($item->getPriceInMinorUnits() * $quantity) / 100,
                'weight' => $item->getWeight() ?? 0,
                'selected' => true,
                'total_weight' => ($item->getWeight() ?? 0) * $quantity,
                'base_total_weight' => ($item->getWeight() ?? 0) * $quantity,
            ];
            $this->storage->addCartItem($cart->id, $itemData);
        }
    }
}
