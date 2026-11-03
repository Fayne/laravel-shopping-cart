<?php

namespace Fayne\ShoppingCart\Storage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;
use Fayne\ShoppingCart\Models\ShoppingCartItem;
use Illuminate\Support\Facades\DB;

/**
 * 缓存存储层实现
 * 保留缓存驱动接口；可变购物车状态始终读取权威存储
 */
class CacheCartStorage implements CartStorageInterface
{
    protected CartStorageInterface $storage;
    protected string $cachePrefix;
    protected int $cacheTtl;
    protected string $cacheStore;

    public function __construct(
        CartStorageInterface $storage,
        string $cachePrefix = 'cart',
        int $cacheTtl = 3600,
        string $cacheStore = 'default'
    ) {
        $this->storage = $storage;
        $this->cachePrefix = $cachePrefix;
        $this->cacheTtl = $cacheTtl;
        $this->cacheStore = $cacheStore;
    }

    /**
     * 获取购物车
     */
    public function getCart(string $customerId, bool $isCheckout = false): ?CartModel
    {
        // Model snapshots cannot be trusted after failed invalidation or catalog changes.
        // Keep the driver API, but read mutable state from its authoritative storage.
        return $this->storage->getCart($customerId, $isCheckout);
    }

    /**
     * 创建购物车
     */
    public function createCart(array $data): CartModel
    {
        $cart = $this->storage->createCart($data);

        // 清除相关缓存
        $this->clearCartCache($data['customer_id']);

        return $cart;
    }

    /**
     * 更新购物车
     */
    public function updateCart(CartModel $cart): bool
    {
        $result = $this->storage->updateCart($cart);

        if ($result) {
            $this->clearCartCache($cart->customer_id);
        }

        return $result;
    }

    /**
     * 删除购物车
     */
    public function deleteCart(string $customerId): bool
    {
        $result = $this->storage->deleteCart($customerId);

        if ($result) {
            $this->clearCartCache($customerId);
        }

        return $result;
    }

    /**
     * 添加购物车项目
     */
    public function addCartItem(int|string $cartId, array $itemData): ShoppingCartItem
    {
        $item = $this->storage->addCartItem($cartId, $itemData);

        // 清除购物车缓存
        $this->clearCartCacheByCartId($cartId);

        return $item;
    }

    /**
     * 更新购物车项目
     */
    public function updateCartItem(int|string $itemId, array $data): bool
    {
        $result = $this->storage->updateCartItem($itemId, $data);

        if ($result) {
            $this->clearCartItemCache($itemId);
            if ($item = $this->storage->getCartItem($itemId)) {
                $this->clearCartCacheByCartId($item->cart_id);
            }
        }

        return $result;
    }

    /**
     * 删除购物车项目
     */
    public function removeCartItem(int|string $itemId): bool
    {
        // 先获取项目信息以便清除缓存
        $item = $this->storage->getCartItem($itemId);
        $result = $this->storage->removeCartItem($itemId);

        if ($result && $item) {
            $this->clearCartItemCache($itemId);
            $this->clearCartCacheByCartId($item->cart_id);
        }

        return $result;
    }

    /**
     * 获取购物车项目
     */
    public function getCartItem(int|string $itemId): ?ShoppingCartItem
    {
        return $this->storage->getCartItem($itemId);
    }

    /**
     * 清空购物车
     */
    public function emptyCart(int|string $cartId): bool
    {
        $result = $this->storage->emptyCart($cartId);

        if ($result) {
            $this->clearCartCacheByCartId($cartId);
        }

        return $result;
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleAllItems(int|string $cartId, bool $selected): bool
    {
        $result = $this->storage->toggleAllItems($cartId, $selected);

        if ($result) {
            $this->clearCartCacheByCartId($cartId);
        }

        return $result;
    }

    /**
     * 获取购物车统计信息
     */
    public function getCartStats(int|string $cartId): array
    {
        return $this->storage->getCartStats($cartId);
    }

    /**
     * 批量更新购物车项目
     */
    public function updateItems(int|string $cartId, array $items): bool
    {
        $result = $this->storage->updateItems($cartId, $items);

        if ($result) {
            // 清除相关缓存
            $this->clearCartCacheByCartId($cartId);
            foreach ($items as $itemData) {
                if (isset($itemData['id'])) {
                    $this->clearCartItemCache($itemData['id']);
                }
            }
        }

        return $result;
    }

    /**
     * 生成购物车缓存键
     */
    protected function getCartCacheKey(string $customerId, bool $isCheckout = false): string
    {
        $suffix = $isCheckout ? '_checkout' : '';

        return "{$this->cachePrefix}:cart:{$customerId}{$suffix}";
    }

    /**
     * 生成购物车项目缓存键
     */
    protected function getCartItemCacheKey(int|string $itemId): string
    {
        return "{$this->cachePrefix}:item:{$itemId}";
    }

    /**
     * 生成购物车统计缓存键
     */
    protected function getCartStatsCacheKey(int|string $cartId): string
    {
        return "{$this->cachePrefix}:stats:{$cartId}";
    }

    /**
     * 清除用户购物车相关缓存
     */
    protected function clearCartCache(int|string $customerId): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->clearCartCache($customerId));
        }
        try {
            $keys = [
                $this->getCartCacheKey($customerId),
                $this->getCartCacheKey($customerId, true),
                "{$this->cachePrefix}:count:{$customerId}",
                "{$this->cachePrefix}:qty:{$customerId}",
            ];

            foreach ($keys as $key) {
                Cache::store($this->cacheStore)->forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart cache', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 根据购物车ID清除缓存
     */
    protected function clearCartCacheByCartId(int|string $cartId): void
    {
        try {
            // 清除统计缓存
            Cache::store($this->cacheStore)->forget($this->getCartStatsCacheKey($cartId));

            if ($cart = CartModel::find($cartId)) {
                $this->clearCartCache($cart->customer_id);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart cache by cart id', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清除购物车项目缓存
     */
    protected function clearCartItemCache(int|string $itemId): void
    {
        try {
            Cache::store($this->cacheStore)->forget($this->getCartItemCacheKey($itemId));
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart item cache', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取用户购物车商品数量
     */
    public function getItemsCount(int|string $customerId): int
    {
        return $this->storage->getItemsCount($customerId);
    }

    /**
     * 获取用户购物车商品总数量
     */
    public function getItemsQty(int|string $customerId): int
    {
        return $this->storage->getItemsQty($customerId);
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleItemsSelection(int|string $cartId, array $itemIds, bool $selected): bool
    {
        foreach ($itemIds as $id) {
            $item = $this->storage->getCartItem($id);
            if (!$item || (string) $item->cart_id !== (string) $cartId) {
                return false;
            }
        }

        return $this->updateItems($cartId, array_map(fn ($id) => ['id' => $id, 'selected' => $selected], $itemIds));
    }

    /**
     * 清除购物车项目缓存
     */
    protected function clearCartItemsCache(int|string $cartId): void
    {
        try {
            Cache::store($this->cacheStore)->forget("{$this->cachePrefix}:items:{$cartId}");
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart items cache', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
