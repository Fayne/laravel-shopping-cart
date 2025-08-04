<?php

namespace MobileNowGroup\ShoppingCart\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;

/**
 * 购物车缓存优化器
 * 提供智能缓存策略和性能优化功能
 */
class CartCacheOptimizer
{
    /**
     * 缓存前缀
     */
    protected string $prefix;

    /**
     * 默认TTL（秒）
     */
    protected int $defaultTtl;

    /**
     * 缓存存储
     */
    protected string $store;

    /**
     * 性能监控开关
     */
    protected bool $enableMonitoring;

    /**
     * 构造函数
     */
    public function __construct(?string $store = null)
    {
        $this->prefix = config('shopping-cart.storage.cache.prefix', 'cart');
        $this->defaultTtl = config('shopping-cart.storage.cache.ttl', 3600);
        $this->store = $store ?? config('shopping-cart.storage.cache.store', 'default');
        $this->enableMonitoring = config('shopping-cart.storage.enable_monitoring', false);
    }

    /**
     * 获取缓存键
     *
     * @param string $type
     * @param string $identifier
     * @return string
     */
    public function getCacheKey(string $type, string $identifier): string
    {
        return "{$this->prefix}:{$type}:{$identifier}";
    }

    /**
     * 智能缓存购物车数据
     *
     * @param string $customerId
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function rememberCart(string $customerId, callable $callback, ?int $ttl = null): mixed
    {
        $key = $this->getCacheKey('cart', $customerId);
        $ttl = $ttl ?? $this->calculateDynamicTtl($customerId);
        
        $startTime = microtime(true);
        
        try {
            $result = Cache::store($this->store)->remember($key, $ttl, $callback);
            
            if ($this->enableMonitoring) {
                $this->recordCacheHit($key, microtime(true) - $startTime, 'hit');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            if ($this->enableMonitoring) {
                $this->recordCacheHit($key, microtime(true) - $startTime, 'miss');
            }
            
            Log::warning('Cache operation failed, falling back to direct call', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            return $callback();
        }
    }

    /**
     * 智能缓存购物车项目
     *
     * @param string $cartId
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function rememberCartItems(string $cartId, callable $callback, ?int $ttl = null): mixed
    {
        $key = $this->getCacheKey('items', $cartId);
        $ttl = $ttl ?? $this->defaultTtl;
        
        return Cache::store($this->store)->remember($key, $ttl, $callback);
    }

    /**
     * 缓存购物车统计信息
     *
     * @param string $customerId
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function rememberCartStats(string $customerId, callable $callback, ?int $ttl = null): mixed
    {
        $key = $this->getCacheKey('stats', $customerId);
        $ttl = $ttl ?? ($this->defaultTtl / 2); // 统计信息缓存时间较短
        
        return Cache::store($this->store)->remember($key, $ttl, $callback);
    }

    /**
     * 批量预热缓存
     *
     * @param array $customerIds
     * @param CartStorageInterface $storage
     * @return array
     */
    public function warmupCache(array $customerIds, CartStorageInterface $storage): array
    {
        $results = [];
        $startTime = microtime(true);
        
        foreach ($customerIds as $customerId) {
            try {
                // 预热购物车缓存
                $this->rememberCart($customerId, function () use ($storage, $customerId) {
                    return $storage->getCart($customerId);
                });
                
                // 预热统计信息缓存
                $this->rememberCartStats($customerId, function () use ($storage, $customerId) {
                    return [
                        'items_count' => $storage->getItemsCount($customerId),
                        'items_qty' => $storage->getItemsQty($customerId),
                    ];
                });
                
                $results[$customerId] = 'success';
                
            } catch (\Exception $e) {
                $results[$customerId] = 'failed: ' . $e->getMessage();
                
                Log::warning('Cache warmup failed for customer', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        if ($this->enableMonitoring) {
            Log::info('Cache warmup completed', [
                'customers_count' => count($customerIds),
                'success_count' => count(array_filter($results, fn($r) => $r === 'success')),
                'duration' => microtime(true) - $startTime,
                'results' => $results
            ]);
        }
        
        return $results;
    }

    /**
     * 清除购物车相关缓存
     *
     * @param string $customerId
     * @param string|null $cartId
     * @return bool
     */
    public function clearCartCache(string $customerId, ?string $cartId = null): bool
    {
        $keys = [
            $this->getCacheKey('cart', $customerId),
            $this->getCacheKey('stats', $customerId),
        ];
        
        if ($cartId) {
            $keys[] = $this->getCacheKey('items', $cartId);
        }
        
        $success = true;
        
        foreach ($keys as $key) {
            try {
                Cache::store($this->store)->forget($key);
            } catch (\Exception $e) {
                $success = false;
                Log::warning('Failed to clear cache key', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $success;
    }

    /**
     * 批量清除缓存
     *
     * @param array $customerIds
     * @return array
     */
    public function clearBatchCache(array $customerIds): array
    {
        $results = [];
        
        foreach ($customerIds as $customerId) {
            $results[$customerId] = $this->clearCartCache($customerId);
        }
        
        return $results;
    }

    /**
     * 计算动态TTL
     * 根据用户活跃度调整缓存时间
     *
     * @param string $customerId
     * @return int
     */
    protected function calculateDynamicTtl(string $customerId): int
    {
        try {
            // 获取用户最近活动时间
            $lastActivity = Cache::store($this->store)->get(
                $this->getCacheKey('last_activity', $customerId)
            );
            
            if (!$lastActivity) {
                return $this->defaultTtl;
            }
            
            $lastActivityTime = \Carbon\Carbon::parse($lastActivity);
            $hoursSinceActivity = $lastActivityTime->diffInHours(now());
            
            // 根据活跃度调整TTL
            if ($hoursSinceActivity < 1) {
                // 非常活跃：延长缓存时间
                return $this->defaultTtl * 2;
            } elseif ($hoursSinceActivity < 24) {
                // 一般活跃：正常缓存时间
                return $this->defaultTtl;
            } else {
                // 不活跃：缩短缓存时间
                return max($this->defaultTtl / 2, 300); // 最少5分钟
            }
            
        } catch (\Exception $e) {
            Log::debug('Failed to calculate dynamic TTL, using default', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            
            return $this->defaultTtl;
        }
    }

    /**
     * 记录缓存命中情况
     *
     * @param string $key
     * @param float $duration
     * @param string $type
     * @return void
     */
    protected function recordCacheHit(string $key, float $duration, string $type): void
    {
        try {
            $store = Cache::store($this->store);
            
            if ($type === 'hit') {
                $store->increment('cart:monitor:hits');
                // 根据缓存键类型增加特定计数器
                if (strpos($key, ':cart:') !== false) {
                    $store->increment('cart:monitor:hits:cart');
                } elseif (strpos($key, ':items:') !== false) {
                    $store->increment('cart:monitor:hits:items');
                }
            } else {
                $store->increment('cart:monitor:misses');
                // 根据缓存键类型增加特定计数器
                if (strpos($key, ':cart:') !== false) {
                    $store->increment('cart:monitor:misses:cart');
                } elseif (strpos($key, ':items:') !== false) {
                    $store->increment('cart:monitor:misses:items');
                }
            }
            
        } catch (\Exception $e) {
            Log::debug('Failed to record cache hit stats', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取缓存统计信息
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        try {
            $totalHits = Cache::store($this->store)->get('cart:monitor:hits', 0);
            $totalMisses = Cache::store($this->store)->get('cart:monitor:misses', 0);
            $cartHits = Cache::store($this->store)->get('cart:monitor:hits:cart', 0);
            $itemsHits = Cache::store($this->store)->get('cart:monitor:hits:items', 0);
            
            $totalOperations = $totalHits + $totalMisses;
            $hitRate = $totalOperations > 0 ? ($totalHits / $totalOperations) * 100 : 0;
            
            return [
                'total_hits' => $totalHits,
                'total_misses' => $totalMisses,
                'hit_rate' => round($hitRate, 2),
                'total_operations' => $totalOperations,
                'cart_hits' => $cartHits,
                'items_hits' => $itemsHits,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', ['error' => $e->getMessage()]);
            return [
                'total_hits' => 0,
                'total_misses' => 0,
                'hit_rate' => 0,
                'total_operations' => 0,
                'cart_hits' => 0,
                'items_hits' => 0,
            ];
        }
    }

    /**
     * 清理过期的监控数据
     *
     * @return bool
     */
    public function cleanupMonitoringData(): bool
    {
        try {
            $pattern = $this->getCacheKey('monitoring', '*');
            
            if ($this->store === 'redis') {
                $keys = Redis::connection()->keys($pattern);
                if (!empty($keys)) {
                    Redis::connection()->del($keys);
                }
            } else {
                // 对于其他缓存驱动，只清理已知的监控键
                $monitoringKeys = [
                    $this->getCacheKey('monitoring', 'cache_stats'),
                    $this->getCacheKey('monitoring', 'performance_data'),
                ];
                
                foreach ($monitoringKeys as $key) {
                    Cache::store($this->store)->forget($key);
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup monitoring data', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * 更新用户活动时间
     *
     * @param string $customerId
     * @return void
     */
    public function updateLastActivity(string $customerId): void
    {
        $key = $this->getCacheKey('last_activity', $customerId);
        
        try {
            Cache::store($this->store)->put($key, now()->toISOString(), now()->addDays(7));
        } catch (\Exception $e) {
            Log::debug('Failed to update last activity', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 预加载购物车数据
     *
     * @param array $customerIds
     * @return array
     */
    public function preloadCartData(array $customerIds): array
    {
        $result = ['preloaded' => 0, 'failed' => 0];
        
        foreach ($customerIds as $customerId) {
            try {
                $cacheKeys = [
                    $this->getCacheKey('cart', $customerId),
                    $this->getCacheKey('items', $customerId),
                    $this->getCacheKey('metadata', $customerId)
                ];
                
                foreach ($cacheKeys as $key) {
                    Cache::store($this->store)->remember($key, $this->defaultTtl, function () {
                        return null; // Placeholder for actual data loading
                    });
                }
                
                $result['preloaded']++;
            } catch (\Exception $e) {
                $result['failed']++;
                Log::debug('Failed to preload cart data', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $result;
    }

    /**
     * 批量清理缓存
     *
     * @param array $customerIds
     * @return array
     */
    public function bulkClearCache(array $customerIds): array
    {
        $result = ['cleared' => 0, 'failed' => 0];
        
        foreach ($customerIds as $customerId) {
            try {
                // 为每个用户清理cart和stats缓存
                Cache::store($this->store)->forget($this->getCacheKey('cart', $customerId));
                Cache::store($this->store)->forget($this->getCacheKey('stats', $customerId));
                
                $result['cleared']++;
            } catch (\Exception $e) {
                $result['failed']++;
                Log::debug('Failed to clear cache for customer', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $result;
     }

    /**
     * 缓存购物车数据
     *
     * @param string $customerId
     * @param mixed $cart
     * @return bool
     */
    public function cacheCart(string $customerId, $cart): bool
    {
        try {
            $key = $this->getCacheKey('cart', $customerId);
            
            return Cache::store($this->store)->put($key, $cart, $this->defaultTtl);
        } catch (\Exception $e) {
            Log::error('Failed to cache cart data', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * 缓存购物车统计数据
     *
     * @param string $customerId
     * @param array $stats
     * @return bool
     */
    public function cacheCartStats(string $customerId, array $stats): bool
    {
        try {
            $key = $this->getCacheKey('stats', $customerId);
            
            return Cache::store($this->store)->put($key, $stats, $this->defaultTtl);
        } catch (\Exception $e) {
            Log::error('Failed to cache cart stats', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            
            return false;
         }
     }

    /**
     * 清理过期的监控数据
     *
     * @return bool
     */
    public function cleanExpiredMonitoringData(): bool
    {
        try {
            // 清理4个监控相关的缓存键
            $monitoringKeys = [
                'cart:monitor:hits',
                'cart:monitor:misses', 
                'cart:monitor:performance',
                'cart:monitor:stats'
            ];
            
            foreach ($monitoringKeys as $key) {
                Cache::store($this->store)->forget($key);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup monitoring data', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * 更新用户活动
     *
     * @param string $customerId
     * @return bool
     */
    public function updateUserActivity(string $customerId): bool
    {
        try {
            $key = $this->getCacheKey('activity', $customerId);
            $timestamp = now()->timestamp;
            
            return Cache::store($this->store)->put($key, $timestamp, 86400);
        } catch (\Exception $e) {
            Log::error('Failed to update user activity', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            
            return false;
         }
     }

    /**
     * 缓存购物车商品
     *
     * @param string $cartId
     * @param mixed $items
     * @return bool
     */
    public function cacheCartItems(string $cartId, $items): bool
    {
        try {
            $key = $this->getCacheKey('items', $cartId);
            $ttl = $this->defaultTtl;
            
            return Cache::store($this->store)->put($key, $items, $ttl);
        } catch (\Exception $e) {
            Log::error('Failed to cache cart items', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
   }