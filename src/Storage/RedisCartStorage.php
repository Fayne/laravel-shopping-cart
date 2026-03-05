<?php

namespace MobileNowGroup\ShoppingCart\Storage;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;

/**
 * Redis存储层实现
 * 适用于分布式环境和高并发场景
 */
class RedisCartStorage implements CartStorageInterface
{
    protected CartStorageInterface $fallbackStorage;
    protected string $keyPrefix;
    protected int $ttl;
    protected string $connection;

    public function __construct(
        CartStorageInterface $fallbackStorage,
        string $keyPrefix = 'cart',
        int $ttl = 86400, // 24小时
        string $connection = 'default'
    ) {
        $this->fallbackStorage = $fallbackStorage;
        $this->keyPrefix = $keyPrefix;
        $this->ttl = $ttl;
        $this->connection = $connection;
    }

    /**
     * 获取购物车
     */
    public function getCart(string $customerId, bool $isCheckout = false): ?CartModel
    {
        try {
            $key = $this->getCartKey($customerId);
            $cartData = Redis::connection($this->connection)->hgetall($key);
            
            if (empty($cartData)) {
                // 从备用存储获取并缓存到Redis
                $cart = $this->fallbackStorage->getCart($customerId, $isCheckout);
                if ($cart) {
                    $this->cacheCart($cart);
                }
                return $cart;
            }
            
            // 从Redis数据重建购物车对象
            return $this->buildCartFromRedisData($cartData, $isCheckout);
            
        } catch (\Exception $e) {
            Log::error('Redis cart storage error', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            
            // 降级到备用存储
            return $this->fallbackStorage->getCart($customerId, $isCheckout);
        }
    }

    /**
     * 创建购物车
     */
    public function createCart(array $data): CartModel
    {
        $cart = $this->fallbackStorage->createCart($data);
        $this->cacheCart($cart);
        return $cart;
    }

    /**
     * 更新购物车
     */
    public function updateCart(CartModel $cart): bool
    {
        $result = $this->fallbackStorage->updateCart($cart);
        
        if ($result) {
            $this->cacheCart($cart);
        }
        
        return $result;
    }

    /**
     * 删除购物车
     */
    public function deleteCart(int|string $customerId): bool
    {
        $result = $this->fallbackStorage->deleteCart($customerId);
        
        if ($result) {
            $this->clearCartFromRedis($customerId);
        }
        
        return $result;
    }

    /**
     * 添加购物车项目
     */
    public function addCartItem(int|string $cartId, array $itemData): ShoppingCartItem
    {
        $item = $this->fallbackStorage->addCartItem($cartId, $itemData);
        
        // 更新Redis中的购物车数据
        $this->updateCartItemInRedis($cartId, $item);
        
        return $item;
    }

    /**
     * 更新购物车项目
     */
    public function updateCartItem(int|string $itemId, array $data): bool
    {
        $result = $this->fallbackStorage->updateCartItem($itemId, $data);
        
        if ($result) {
            $item = $this->fallbackStorage->getCartItem($itemId);
            if ($item) {
                $this->updateCartItemInRedis($item->cart_id, $item);
            }
        }
        
        return $result;
    }

    /**
     * 删除购物车项目
     */
    public function removeCartItem(int|string $itemId): bool
    {
        $item = $this->fallbackStorage->getCartItem($itemId);
        $result = $this->fallbackStorage->removeCartItem($itemId);
        
        if ($result && $item) {
            $this->removeCartItemFromRedis($item->cart_id, $itemId);
        }
        
        return $result;
    }

    /**
     * 获取购物车项目
     */
    public function getCartItem(int|string $itemId): ?ShoppingCartItem
    {
        try {
            $key = $this->getCartItemKey($itemId);
            $itemData = Redis::connection($this->connection)->hgetall($key);
            
            if (empty($itemData)) {
                $item = $this->fallbackStorage->getCartItem($itemId);
                if ($item) {
                    $this->cacheCartItem($item);
                }
                return $item;
            }
            
            return $this->buildCartItemFromRedisData($itemData);
            
        } catch (\Exception $e) {
            Log::error('Redis cart item storage error', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
            
            return $this->fallbackStorage->getCartItem($itemId);
        }
    }

    /**
     * 清空购物车
     */
    public function emptyCart(int|string $cartId): bool
    {
        $result = $this->fallbackStorage->emptyCart($cartId);
        
        if ($result) {
            $this->clearCartItemsFromRedis($cartId);
        }
        
        return $result;
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleAllItems(int|string $cartId, bool $selected): bool
    {
        $result = $this->fallbackStorage->toggleAllItems($cartId, $selected);
        
        if ($result) {
            $this->updateCartItemsSelectionInRedis($cartId, $selected);
        }
        
        return $result;
    }

    /**
     * 获取购物车统计信息
     */
    public function getCartStats(int|string $cartId): array
    {
        try {
            $key = $this->getCartStatsKey($cartId);
            $stats = Redis::connection($this->connection)->hgetall($key);
            
            if (empty($stats)) {
                $stats = $this->fallbackStorage->getCartStats($cartId);
                $this->cacheCartStats($cartId, $stats);
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Redis cart stats error', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
            
            return $this->fallbackStorage->getCartStats($cartId);
        }
    }

    /**
     * 批量更新购物车项目
     */
    public function updateItems(int|string $cartId, array $items): bool
    {
        $result = $this->fallbackStorage->updateItems($cartId, $items);
        
        if ($result) {
            // 清除相关的Redis缓存
            $this->clearCartItemsFromRedis($cartId);
        }
        
        return $result;
    }

    /**
     * 生成购物车Redis键
     */
    protected function getCartKey(int|string $customerId): string
    {
        return "{$this->keyPrefix}:cart:{$customerId}";
    }

    /**
     * 生成购物车项目Redis键
     */
    protected function getCartItemKey(int|string $itemId): string
    {
        return "{$this->keyPrefix}:item:{$itemId}";
    }

    /**
     * 生成购物车统计Redis键
     */
    protected function getCartStatsKey(int|string $cartId): string
    {
        return "{$this->keyPrefix}:stats:{$cartId}";
    }

    /**
     * 生成购物车项目列表Redis键
     */
    protected function getCartItemsKey(int|string $cartId): string
    {
        return "{$this->keyPrefix}:items:{$cartId}";
    }

    /**
     * 缓存购物车到Redis
     */
    protected function cacheCart(CartModel $cart): void
    {
        try {
            $key = $this->getCartKey($cart->customer_id);
            $data = $cart->toArray();
            
            Redis::connection($this->connection)->hmset($key, $data);
            Redis::connection($this->connection)->expire($key, $this->ttl);
            
            // 缓存购物车项目
            foreach ($cart->items as $item) {
                $this->cacheCartItem($item);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to cache cart to Redis', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 缓存购物车项目到Redis
     */
    protected function cacheCartItem(ShoppingCartItem $item): void
    {
        try {
            $key = $this->getCartItemKey($item->id);
            $data = $item->toArray();
            
            Redis::connection($this->connection)->hmset($key, $data);
            Redis::connection($this->connection)->expire($key, $this->ttl);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache cart item to Redis', [
                'item_id' => $item->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 缓存购物车统计信息
     */
    protected function cacheCartStats(int|string $cartId, array $stats): void
    {
        try {
            $key = $this->getCartStatsKey($cartId);
            
            Redis::connection($this->connection)->hmset($key, $stats);
            Redis::connection($this->connection)->expire($key, $this->ttl / 2);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache cart stats to Redis', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 从Redis数据构建购物车对象
     */
    protected function buildCartFromRedisData(array $data, bool $isCheckout): ?CartModel
    {
        // 这里需要根据实际需求实现从Redis数据重建购物车对象的逻辑
        // 简化实现，实际项目中可能需要更复杂的序列化/反序列化逻辑
        $cart = new CartModel($data);
        $cart->exists = true;
        
        return $cart;
    }

    /**
     * 从Redis数据构建购物车项目对象
     */
    protected function buildCartItemFromRedisData(array $data): ?ShoppingCartItem
    {
        $item = new ShoppingCartItem($data);
        $item->exists = true;
        
        return $item;
    }

    /**
     * 从Redis清除购物车
     */
    protected function clearCartFromRedis(int|string $customerId): void
    {
        try {
            $key = $this->getCartKey($customerId);
            Redis::connection($this->connection)->del($key);
        } catch (\Exception $e) {
            Log::error('Failed to clear cart from Redis', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 更新Redis中的购物车项目
     */
    protected function updateCartItemInRedis(int|string $cartId, ShoppingCartItem $item): void
    {
        $this->cacheCartItem($item);
        // 可以在这里更新购物车的统计信息
    }

    /**
     * 从Redis移除购物车项目
     */
    protected function removeCartItemFromRedis(int|string $cartId, int|string $itemId): void
    {
        try {
            $key = $this->getCartItemKey($itemId);
            Redis::connection($this->connection)->del($key);
        } catch (\Exception $e) {
            Log::error('Failed to remove cart item from Redis', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 清除Redis中的购物车项目
     */
    protected function clearCartItemsFromRedis(int|string $cartId): void
    {
        try {
            Redis::connection($this->connection)->del(["cart:items:{$cartId}"]);
        } catch (\Exception $e) {
            Log::warning('Failed to clear cart items from Redis', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 更新Redis中购物车项目的选中状态
     */
    protected function updateCartItemsSelectionInRedis(int|string $cartId, bool $selected): void
    {
        // 实现批量更新购物车项目选中状态的逻辑
        // 这里需要根据实际的Redis键结构来实现
    }

    /**
     * 获取用户购物车商品数量
     */
    public function getItemsCount(int|string $customerId): int
    {
        try {
            $cart = $this->getCart($customerId);
            return $cart ? $cart->items->count() : 0;
        } catch (\Exception $e) {
            Log::error('Failed to get items count from Redis', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->getItemsCount($customerId);
        }
    }

    /**
     * 获取用户购物车商品总数量
     */
    public function getItemsQty(int|string $customerId): int
    {
        try {
            $cart = $this->getCart($customerId);
            return $cart ? $cart->items->sum('quantity') : 0;
        } catch (\Exception $e) {
            Log::error('Failed to get items qty from Redis', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->getItemsQty($customerId);
        }
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleItemsSelection(int|string $cartId, array $itemIds, bool $selected): bool
    {
        try {
            $result = $this->fallbackStorage->toggleItemsSelection($cartId, $itemIds, $selected);
            
            if ($result) {
                // 清除购物车项目缓存
                $this->clearCartItemsCache($cartId);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to toggle items selection in Redis', [
                'cart_id' => $cartId,
                'item_ids' => $itemIds,
                'selected' => $selected,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->toggleItemsSelection($cartId, $itemIds, $selected);
        }
    }

    /**
     * 获取购物车项目列表
     */
    public function getCartItems(int|string $cartId): array
    {
        try {
            $key = "cart:items:{$cartId}";
            $data = Redis::connection($this->connection)->get($key);
            
            if (!$data) {
                return $this->fallbackStorage->getCartItems($cartId);
            }
            
            $itemsData = json_decode($data, true);
            $items = [];
            
            foreach ($itemsData as $itemData) {
                $items[] = new ShoppingCartItem($itemData);
            }
            
            return $items;
        } catch (\Exception $e) {
            Log::error('Failed to get cart items from Redis', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->getCartItems($cartId);
        }
    }

    /**
     * 移除购物车项目
     */
    public function removeItem(int|string $cartId, int|string $itemId): bool
    {
        try {
            $result = $this->fallbackStorage->removeItem($cartId, $itemId);
            
            if ($result) {
                // 清除购物车项目缓存
                $this->clearCartItemsCache($cartId);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to remove item from Redis', [
                'cart_id' => $cartId,
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->removeItem($cartId, $itemId);
        }
    }

    /**
     * 添加购物车项目
     */
    public function addItem(int|string $cartId, array $itemData): ShoppingCartItem
    {
        try {
            $result = $this->fallbackStorage->addItem($cartId, $itemData);
            
            if ($result) {
                // 清除购物车项目缓存
                $this->clearCartItemsCache($cartId);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to add item to Redis', [
                'cart_id' => $cartId,
                'item_data' => $itemData,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackStorage->addItem($cartId, $itemData);
        }
    }

    /**
     * 清除购物车项目缓存
     */
    protected function clearCartItemsCache(int|string $cartId): void
    {
        try {
            Redis::connection($this->connection)->del("cart:items:{$cartId}");
        } catch (\Exception $e) {
            Log::error('Failed to clear cart items cache', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
    }
}