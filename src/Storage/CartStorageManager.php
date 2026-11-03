<?php

namespace Fayne\ShoppingCart\Storage;

use Illuminate\Support\Manager;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;

/**
 * 购物车存储管理器
 * 负责管理不同的存储驱动和策略
 */
class CartStorageManager extends Manager
{
    /**
     * 获取默认驱动名称
     */
    public function getDefaultDriver(): string
    {
        if ($this->container->bound('config')) {
            return $this->container['config']->get('shopping-cart.storage.default') ?: 'database';
        }
        
        return 'database';
    }

    /**
     * 创建数据库存储驱动
     */
    public function createDatabaseDriver(): CartStorageInterface
    {
        return new DatabaseCartStorage();
    }

    /**
     * 创建缓存存储驱动
     */
    public function createCacheDriver(): CartStorageInterface
    {
        $config = [];
        if ($this->container->bound('config')) {
            $config = $this->container['config']->get('shopping-cart.storage.drivers.cache', []);
        }
        
        return new CacheCartStorage(
            $this->createDatabaseDriver(),
            $config['prefix'] ?? 'cart',
            $config['ttl'] ?? 3600,
            $config['store'] ?? 'default'
        );
    }

    /**
     * 创建Redis存储驱动
     */
    public function createRedisDriver(): CartStorageInterface
    {
        $config = [];
        if ($this->container->bound('config')) {
            $config = $this->container['config']->get('shopping-cart.storage.drivers.redis', []);
        }
        
        return new RedisCartStorage(
            $this->createDatabaseDriver(),
            $config['prefix'] ?? 'cart',
            $config['ttl'] ?? 86400,
            $config['connection'] ?? 'default'
        );
    }

    /**
     * 创建分层存储驱动
     */
    public function createLayeredDriver(): CartStorageInterface
    {
        $config = [];
        if ($this->container->bound('config')) {
            $config = $this->container['config']->get('shopping-cart.storage.drivers.layered', []);
        }
        
        // 构建存储层次
        $storage = $this->createDatabaseDriver();
        
        // 添加缓存层
        if ($config['enable_cache'] ?? true) {
            $cacheConfig = $this->container['config']->get('shopping-cart.storage.drivers.cache', []);
            $storage = new CacheCartStorage(
                $storage,
                $cacheConfig['prefix'] ?? 'cart',
                $cacheConfig['ttl'] ?? 3600,
                $cacheConfig['store'] ?? 'default'
            );
        }
        
        // 添加Redis层
        if ($config['enable_redis'] ?? false) {
            $redisConfig = $this->container['config']->get('shopping-cart.storage.drivers.redis', []);
            $storage = new RedisCartStorage(
                $storage,
                $redisConfig['prefix'] ?? 'cart',
                $redisConfig['ttl'] ?? 86400,
                $redisConfig['connection'] ?? 'default'
            );
        }
        
        return $storage;
    }

    /**
     * 创建自定义存储驱动
     */
    protected function createCustomDriver(array $config): CartStorageInterface
    {
        $driver = $config['driver'];
        
        if (class_exists($driver)) {
            return $this->container->make($driver, $config);
        }
        
        throw new \InvalidArgumentException("Storage driver [{$driver}] not found.");
    }
}