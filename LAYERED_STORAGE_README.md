# Laravel Shopping Cart - 分层存储策略

本文档详细介绍了为 Laravel Shopping Cart 包实现的分层存储策略，该策略旨在提高包在大中型项目中的性能和可扩展性。

## 概述

分层存储策略通过引入多个存储层来优化购物车数据的读写性能：

1. **缓存层** - 提供快速数据访问
2. **Redis层** - 支持分布式环境
3. **数据库层** - 确保数据持久性
4. **分层存储** - 智能组合多种存储方式

## 架构设计

### 核心组件

```
┌─────────────────────────────────────────────────────────────┐
│                    ShoppingCart                             │
│                 (主要业务逻辑)                                │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                CartStorageInterface                        │
│                  (存储抽象层)                                │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│               CartStorageManager                           │
│                 (存储管理器)                                 │
└─┬─────────┬─────────┬─────────┬─────────────────────────────┘
  │         │         │         │
  ▼         ▼         ▼         ▼
┌───────┐ ┌───────┐ ┌───────┐ ┌─────────────┐
│Database│ │ Cache │ │ Redis │ │   Layered   │
│Storage │ │Storage│ │Storage│ │   Storage   │
└───────┘ └───────┘ └───────┘ └─────────────┘
```

### 存储接口

`CartStorageInterface` 定义了统一的存储操作接口：

```php
interface CartStorageInterface
{
    public function getCart(string $customerId);
    public function createCart(string $customerId, array $data = []);
    public function updateCart(string $customerId, array $data);
    public function deleteCart(string $customerId): bool;
    public function addItem(string $cartId, array $itemData);
    public function updateItems(string $cartId, array $items): bool;
    public function removeItem(string $cartId, int $itemId): bool;
    public function emptyCart(string $customerId): bool;
    public function toggleItemsSelection(string $cartId, array $itemIds, bool $selected): bool;
    public function getItemsCount(string $customerId): int;
    public function getItemsQty(string $customerId): int;
}
```

## 配置

### 基本配置

在 `config/shopping-cart.php` 中添加存储配置：

```php
'storage' => [
    'default' => env('CART_STORAGE_DRIVER', 'database'),
    
    'drivers' => [
        'database' => [
            'connection' => null, // 使用默认数据库连接
        ],
        
        'cache' => [
            'store' => env('CART_CACHE_STORE', 'default'),
            'prefix' => env('CART_CACHE_PREFIX', 'cart'),
            'ttl' => env('CART_CACHE_TTL', 3600),
        ],
        
        'redis' => [
            'connection' => env('CART_REDIS_CONNECTION', 'default'),
            'prefix' => env('CART_REDIS_PREFIX', 'cart'),
            'ttl' => env('CART_REDIS_TTL', 3600),
        ],
        
        'layered' => [
            'enable_cache' => true,
            'enable_redis' => false,
            'cache_ttl' => 1800,
            'redis_ttl' => 3600,
        ],
    ],
],
```

### 环境变量

在 `.env` 文件中设置：

```env
# 存储驱动选择
CART_STORAGE_DRIVER=layered

# 缓存配置
CART_CACHE_STORE=redis
CART_CACHE_PREFIX=cart
CART_CACHE_TTL=3600

# Redis配置
CART_REDIS_CONNECTION=default
CART_REDIS_PREFIX=cart
CART_REDIS_TTL=7200
```

## 存储驱动详解

### 1. 数据库存储 (DatabaseCartStorage)

**特点：**
- 数据持久化
- 事务支持
- 数据一致性保证

**适用场景：**
- 小型项目
- 数据一致性要求高的场景
- 开发和测试环境

**使用方式：**
```php
// 配置
'storage' => [
    'default' => 'database',
]
```

### 2. 缓存存储 (CacheCartStorage)

**特点：**
- 基于数据库存储
- 添加缓存层提升读取性能
- 自动缓存失效机制

**适用场景：**
- 中型项目
- 读多写少的场景
- 单机部署环境

**使用方式：**
```php
// 配置
'storage' => [
    'default' => 'cache',
    'drivers' => [
        'cache' => [
            'store' => 'redis',
            'ttl' => 3600,
        ],
    ],
]
```

### 3. Redis存储 (RedisCartStorage)

**特点：**
- 高性能内存存储
- 支持分布式部署
- 数据结构丰富
- 支持数据持久化

**适用场景：**
- 大型项目
- 分布式环境
- 高并发场景
- 需要会话共享的集群环境

**使用方式：**
```php
// 配置
'storage' => [
    'default' => 'redis',
    'drivers' => [
        'redis' => [
            'connection' => 'cache',
            'prefix' => 'cart',
            'ttl' => 7200,
        ],
    ],
]
```

### 4. 分层存储 (LayeredCartStorage)

**特点：**
- 智能组合多种存储方式
- 缓存穿透保护
- 自动降级机制
- 性能监控

**存储层级：**
1. **L1缓存** - 应用级缓存（最快）
2. **L2缓存** - Redis缓存（快）
3. **L3存储** - 数据库（可靠）

**读取策略：**
```
请求 → L1缓存 → L2缓存 → 数据库 → 回填缓存
```

**写入策略：**
```
写入数据库 → 更新L2缓存 → 更新L1缓存
```

**使用方式：**
```php
// 配置
'storage' => [
    'default' => 'layered',
    'drivers' => [
        'layered' => [
            'enable_cache' => true,
            'enable_redis' => true,
            'cache_ttl' => 1800,
            'redis_ttl' => 3600,
        ],
    ],
]
```

## 性能优化功能

### 1. 缓存优化器 (CartCacheOptimizer)

提供智能缓存管理功能：

```php
use Fayne\ShoppingCart\Cache\CartCacheOptimizer;

$optimizer = app(CartCacheOptimizer::class);

// 预热缓存
$optimizer->warmupCache(['user1', 'user2', 'user3'], $storage);

// 清除缓存
$optimizer->clearCartCache('user1');

// 获取缓存统计
$stats = $optimizer->getCacheStats();
```

**功能特性：**
- 动态TTL计算（根据用户活跃度）
- 批量缓存预热
- 缓存命中率监控
- 智能缓存清理

### 2. 性能监控 (CartPerformanceMonitoring)

使用性能监控trait：

```php
use Fayne\ShoppingCart\Traits\CartPerformanceMonitoring;

class CustomCartStorage implements CartStorageInterface
{
    use CartPerformanceMonitoring;
    
    public function getCart(string $customerId)
    {
        return $this->monitorPerformance('getCart', function() use ($customerId) {
            // 实际的获取购物车逻辑
            return $this->fetchCartFromStorage($customerId);
        });
    }
}
```

### 3. 事件监听器 (CartOperationListener)

自动处理购物车操作的异步任务：

```php
// 在 EventServiceProvider 中注册
protected $listen = [
    CartAddProductAfter::class => [
        CartOperationListener::class . '@handleProductAdded',
    ],
    CartRemoveItemAfter::class => [
        CartOperationListener::class . '@handleItemRemoved',
    ],
    // ... 其他事件
];
```

**功能包括：**
- 用户行为记录
- 推荐系统更新
- 库存预警检查
- 购物偏好分析

## 命令行工具

### 1. 数据迁移命令

在不同存储驱动之间迁移数据：

```bash
# 从数据库迁移到Redis
php artisan cart:migrate-data --from=database --to=redis

# 干运行模式（不实际迁移）
php artisan cart:migrate-data --from=database --to=redis --dry-run

# 迁移特定用户
php artisan cart:migrate-data --from=database --to=redis --customer-id=123

# 设置批处理大小
php artisan cart:migrate-data --from=database --to=redis --batch-size=50
```

### 2. 性能监控命令

监控和分析购物车性能：

```bash
# 生成性能报告
php artisan cart:monitor --action=report

# 分析性能问题
php artisan cart:monitor --action=analyze

# 运行基准测试
php artisan cart:monitor --action=benchmark --benchmark-iterations=200

# 清理监控数据
php artisan cart:monitor --action=cleanup

# 导出报告
php artisan cart:monitor --action=report --format=json --export=report.json
```

## 使用示例

### 基本使用

```php
use Fayne\ShoppingCart\ShoppingCart;

// 获取购物车实例
$cart = app('shopping-cart');

// 添加商品到购物车
$cart->addProduct($product, 2, ['color' => 'red']);

// 获取购物车
$cartData = $cart->getCart('user123');

// 更新商品数量
$cart->updateItems('cart456', [['id' => 1, 'quantity' => 3]]);

// 移除商品
$cart->removeItem('cart456', 1);

// 清空购物车
$cart->emptyCart('user123');
```

### 高级使用

```php
// 切换存储驱动
$redisStorage = app('cart.storage')->driver('redis');
$cacheStorage = app('cart.storage')->driver('cache');

// 使用特定存储驱动
$cart = new ShoppingCart($redisStorage);

// 批量操作
$optimizer = app(CartCacheOptimizer::class);
$optimizer->warmupCache(['user1', 'user2'], $redisStorage);
```

## 最佳实践

### 1. 存储驱动选择

**小型项目（< 1000 用户）：**
```php
'storage' => ['default' => 'database']
```

**中型项目（1000-10000 用户）：**
```php
'storage' => ['default' => 'cache']
```

**大型项目（> 10000 用户）：**
```php
'storage' => ['default' => 'layered']
```

**分布式环境：**
```php
'storage' => ['default' => 'redis']
```

### 2. 缓存策略

```php
// 根据业务场景调整TTL
'cache' => [
    'ttl' => 1800, // 30分钟，适合活跃用户
],

'redis' => [
    'ttl' => 7200, // 2小时，适合会话保持
],
```

### 3. 性能监控

```php
// 启用性能监控
'storage' => [
    'enable_monitoring' => true,
    'performance_thresholds' => [
        'response_time' => 1000, // 1秒
        'memory_usage' => 50,    // 50MB
    ],
],
```

### 4. 错误处理

```php
// 配置降级策略
'storage' => [
    'drivers' => [
        'layered' => [
            'fallback_driver' => 'database',
            'enable_circuit_breaker' => true,
        ],
    ],
],
```

## 故障排除

### 常见问题

1. **缓存命中率低**
   - 检查TTL设置
   - 优化缓存键策略
   - 考虑预热缓存

2. **Redis连接失败**
   - 检查Redis服务状态
   - 验证连接配置
   - 检查网络连通性

3. **数据不一致**
   - 检查事务配置
   - 验证缓存失效机制
   - 考虑使用强一致性模式

4. **性能问题**
   - 运行性能监控命令
   - 检查数据库查询
   - 优化缓存策略

### 调试工具

```bash
# 检查存储驱动状态
php artisan cart:monitor --action=analyze

# 查看缓存统计
php artisan tinker
>>> app(CartCacheOptimizer::class)->getCacheStats()

# 测试存储性能
php artisan cart:monitor --action=benchmark
```

## 升级指南

### 从旧版本升级

1. **备份数据**
   ```bash
   php artisan cart:migrate-data --from=database --to=database --dry-run
   ```

2. **更新配置**
   ```bash
   php artisan vendor:publish --tag=shopping-cart-config --force
   ```

3. **迁移数据**
   ```bash
   php artisan cart:migrate-data --from=database --to=layered
   ```

4. **验证功能**
   ```bash
   php artisan cart:monitor --action=report
   ```

## 贡献指南

欢迎提交问题和改进建议！请遵循以下步骤：

1. Fork 项目
2. 创建功能分支
3. 提交更改
4. 推送到分支
5. 创建 Pull Request

## 许可证

本项目采用 MIT 许可证。详情请参阅 LICENSE 文件。