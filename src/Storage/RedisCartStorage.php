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
    public function deleteCart(string $customerId): bool
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
    public function addCartItem(int $cartId, array $itemData): ShoppingCartItem
    {
        $item = $this->fallbackStorage->addCartItem($cartId, $itemData);
        
        // 更新Redis中的购物车数据
        $this->updateCartItemInRedis($cartId, $item);
        
        return $item;
    }

    /**
     * 更新购物车项目
     */
    public function updateCartItem(int $itemId, array $data): bool
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
    public function removeCartItem(int $itemId): bool
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
    public function getCartItem(int $itemId): ?ShoppingCartItem
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
    public function emptyCart(int $cartId): bool
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
    public function toggleAllItems(int $cartId, bool $selected): bool
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
    public function getCartStats(int $cartId): array
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
     * 生成购物车Redis键
     */
    protected function getCartKey(string $customerId): string
    {
        return "{$this->keyPrefix}:cart:{$customerId}";
    }

    /**
     * 生成购物车项目Redis键
     */
    protected function getCartItemKey(int $itemId): string
    {
        return "{$this->keyPrefix}:item:{$itemId}";
    }

    /**
     * 生成购物车统计Redis键
     */
    protected function getCartStatsKey(int $cartId): string
    {
        return "{$this->keyPrefix}:stats:{$cartId}";
    }

    /**
     * 生成购物车项目列表Redis键
     */
    protected function getCartItemsKey(int $cartId): string
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
    protected function cacheCartStats(int $cartId, array $stats): void
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
    protected function clearCartFromRedis(string $customerId): void
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
    protected function updateCartItemInRedis(int $cartId, ShoppingCartItem $item): void
    {
        $this->cacheCartItem($item);
        // 可以在这里更新购物车的统计信息
    }

    /**
     * 从Redis移除购物车项目
     */
    protected function removeCartItemFromRedis(int $cartId, int $itemId): void
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
    protected function clearCartItemsFromRedis(int $cartId): void
    {
        // 实现清除购物车所有项目的逻辑
        // 这里需要根据实际的Redis键结构来实现
    }

    /**
     * 更新Redis中购物车项目的选中状态
     */
    protected function updateCartItemsSelectionInRedis(int $cartId, bool $selected): void
    {
        // 实现批量更新购物车项目选中状态的逻辑
        // 这里需要根据实际的Redis键结构来实现
    }
}