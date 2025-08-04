<?php

namespace MobileNowGroup\ShoppingCart\Storage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

/**
 * 缓存存储层实现
 * 在数据库存储基础上增加缓存层，提高性能
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
        $cacheKey = $this->getCartCacheKey($customerId, $isCheckout);
        
        return Cache::store($this->cacheStore)->remember(
            $cacheKey,
            $this->cacheTtl,
            fn() => $this->storage->getCart($customerId, $isCheckout)
        );
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
    public function addCartItem(int $cartId, array $itemData): ShoppingCartItem
    {
        $item = $this->storage->addCartItem($cartId, $itemData);
        
        // 清除购物车缓存
        $this->clearCartCacheByCartId($cartId);
        
        return $item;
    }

    /**
     * 更新购物车项目
     */
    public function updateCartItem(int $itemId, array $data): bool
    {
        $result = $this->storage->updateCartItem($itemId, $data);
        
        if ($result) {
            $this->clearCartItemCache($itemId);
        }
        
        return $result;
    }

    /**
     * 删除购物车项目
     */
    public function removeCartItem(int $itemId): bool
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
    public function getCartItem(int $itemId): ?ShoppingCartItem
    {
        $cacheKey = $this->getCartItemCacheKey($itemId);
        
        return Cache::store($this->cacheStore)->remember(
            $cacheKey,
            $this->cacheTtl,
            fn() => $this->storage->getCartItem($itemId)
        );
    }

    /**
     * 清空购物车
     */
    public function emptyCart(int $cartId): bool
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
    public function toggleAllItems(int $cartId, bool $selected): bool
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
    public function getCartStats(int $cartId): array
    {
        $cacheKey = $this->getCartStatsCacheKey($cartId);
        
        return Cache::store($this->cacheStore)->remember(
            $cacheKey,
            $this->cacheTtl / 2, // 统计信息缓存时间较短
            fn() => $this->storage->getCartStats($cartId)
        );
    }

    /**
     * 批量更新购物车项目
     */
    public function updateItems(int $cartId, array $items): bool
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
    protected function getCartItemCacheKey(int $itemId): string
    {
        return "{$this->cachePrefix}:item:{$itemId}";
    }

    /**
     * 生成购物车统计缓存键
     */
    protected function getCartStatsCacheKey(int $cartId): string
    {
        return "{$this->cachePrefix}:stats:{$cartId}";
    }

    /**
     * 清除用户购物车相关缓存
     */
    protected function clearCartCache(string $customerId): void
    {
        try {
            $keys = [
                $this->getCartCacheKey($customerId),
                $this->getCartCacheKey($customerId, true),
            ];
            
            foreach ($keys as $key) {
                Cache::store($this->cacheStore)->forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart cache', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 根据购物车ID清除缓存
     */
    protected function clearCartCacheByCartId(int $cartId): void
    {
        try {
            // 清除统计缓存
            Cache::store($this->cacheStore)->forget($this->getCartStatsCacheKey($cartId));
            
            // 这里可以根据需要实现更复杂的缓存清除逻辑
            // 比如通过购物车ID找到customer_id然后清除相关缓存
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart cache by cart id', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 清除购物车项目缓存
     */
    protected function clearCartItemCache(int $itemId): void
    {
        try {
            Cache::store($this->cacheStore)->forget($this->getCartItemCacheKey($itemId));
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart item cache', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取用户购物车商品数量
     */
    public function getItemsCount(string $customerId): int
    {
        $cacheKey = "{$this->cachePrefix}:stats:{$customerId}";
        
        return Cache::store($this->cacheStore)->remember(
            $cacheKey,
            $this->cacheTtl / 2,
            fn() => $this->storage->getItemsCount($customerId)
        );
    }

    /**
     * 获取用户购物车商品总数量
     */
    public function getItemsQty(string $customerId): int
    {
        $cacheKey = "{$this->cachePrefix}:stats:{$customerId}";
        
        return Cache::store($this->cacheStore)->remember(
            $cacheKey,
            $this->cacheTtl / 2,
            fn() => $this->storage->getItemsQty($customerId)
        );
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleItemsSelection(int $cartId, array $itemIds, bool $selected): bool
    {
        $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);
        
        if ($result) {
            // 清除购物车项目缓存
            $this->clearCartItemsCache($cartId);
        }
        
        return $result;
    }

    /**
     * 清除购物车项目缓存
     */
    protected function clearCartItemsCache(int $cartId): void
    {
        try {
            Cache::store($this->cacheStore)->forget("{$this->cachePrefix}:items:{$cartId}");
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart items cache', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
    }
}