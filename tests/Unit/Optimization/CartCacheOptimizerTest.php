<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->optimizer = new CartCacheOptimizer();
});

afterEach(function () {
    Mockery::close();
});

test('it generates correct cache keys', function () {
    $customerId = 'user123';
    $cartId = '1';
    
    $cartKey = $this->optimizer->getCacheKey('cart', $customerId);
    $itemsKey = $this->optimizer->getCacheKey('items', $cartId);
    $statsKey = $this->optimizer->getCacheKey('stats', $customerId);
    
    expect($cartKey)->toBe('cart:cart:user123');
    expect($itemsKey)->toBe('cart:items:1');
    expect($statsKey)->toBe('cart:stats:user123');
});

test('it can cache cart data', function () {
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('put')
        ->once()
        ->with('cart:cart:user123', $cart, Mockery::type('int'))
        ->andReturn(true);

    $result = $this->optimizer->cacheCart($customerId, $cart);

    expect($result)->toBeTrue();
});

test('it can cache cart items', function () {
    $cartId = '1';
    $items = collect([
        new ShoppingCartItem([
            'id' => 1,
            'cart_id' => $cartId,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ])
    ]);
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('put')
        ->once()
        ->with('cart:items:1', $items, Mockery::type('int'))
        ->andReturn(true);

    $result = $this->optimizer->cacheCartItems($cartId, $items);

    expect($result)->toBeTrue();
});

test('it can cache cart stats', function () {
    $customerId = 'user123';
    $stats = ['items_count' => 5, 'items_qty' => 10];
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('put')
        ->once()
        ->with('cart:stats:user123', $stats, Mockery::type('int'))
        ->andReturn(true);

    $result = $this->optimizer->cacheCartStats($customerId, $stats);

    expect($result)->toBeTrue();
});

test('it can preload cart data', function () {
    $customerIds = ['user123', 'user456'];
    
    Cache::shouldReceive('store')
        ->times(6) // 2 customers * 3 cache operations each
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('remember')
        ->times(6)
        ->andReturn(null); // Simulate cache miss

    $result = $this->optimizer->preloadCartData($customerIds);

    expect($result)->toBeArray()
        ->toHaveKey('preloaded')
        ->toHaveKey('failed');
});

test('it can clear cart cache', function () {
    $customerId = 'user123';
    $cartId = '1';
    
    Cache::shouldReceive('store')
        ->times(3)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with('cart:cart:user123')
        ->andReturn(true);
        
    Cache::shouldReceive('forget')
        ->once()
        ->with('cart:items:1')
        ->andReturn(true);
        
    Cache::shouldReceive('forget')
        ->once()
        ->with('cart:stats:user123')
        ->andReturn(true);

    $result = $this->optimizer->clearCartCache($customerId, $cartId);

    expect($result)->toBeTrue();
});

test('it can bulk clear cache', function () {
    $customerIds = ['user123', 'user456'];
    
    Cache::shouldReceive('store')
        ->times(4) // 2 customers * 2 cache operations each (cart + stats)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->times(4)
        ->andReturn(true);

    $result = $this->optimizer->bulkClearCache($customerIds);

    expect($result)->toBeArray()
        ->toHaveKey('cleared')
        ->toHaveKey('failed');
});

test('it calculates dynamic ttl based on activity', function () {
    $customerId = 'user123';
    
    // Mock recent activity
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:activity:user123')
        ->andReturn(now()->timestamp);

    $ttl = $this->optimizer->calculateDynamicTTL($customerId);

    expect($ttl)->toBeInt()
        ->toBeGreaterThan(0);
});

test('it calculates default ttl for inactive users', function () {
    $customerId = 'user123';
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:activity:user123')
        ->andReturn(null);

    $ttl = $this->optimizer->calculateDynamicTTL($customerId);

    expect($ttl)->toBe(3600); // Default TTL
});

test('it records cache hit', function () {
    $key = 'cart:cart:user123';
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('increment')
        ->once()
        ->with('cart:monitor:hits')
        ->andReturn(1);
        
    Cache::shouldReceive('increment')
        ->once()
        ->with('cart:monitor:hits:cart')
        ->andReturn(1);

    $this->optimizer->recordCacheHit($key);

    // No assertion needed as we're just testing the method doesn't throw
    expect(true)->toBeTrue();
});

test('it records cache miss', function () {
    $key = 'cart:cart:user123';
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('increment')
        ->once()
        ->with('cart:monitor:misses')
        ->andReturn(1);
        
    Cache::shouldReceive('increment')
        ->once()
        ->with('cart:monitor:misses:cart')
        ->andReturn(1);

    $this->optimizer->recordCacheMiss($key);

    // No assertion needed as we're just testing the method doesn't throw
    expect(true)->toBeTrue();
});

test('it gets cache statistics', function () {
    Cache::shouldReceive('store')
        ->times(4)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:monitor:hits', 0)
        ->andReturn(100);
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:monitor:misses', 0)
        ->andReturn(20);
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:monitor:hits:cart', 0)
        ->andReturn(50);
        
    Cache::shouldReceive('get')
        ->once()
        ->with('cart:monitor:hits:items', 0)
        ->andReturn(30);

    $stats = $this->optimizer->getCacheStats();

    expect($stats)->toBeArray()
        ->toHaveKey('total_hits')
        ->toHaveKey('total_misses')
        ->toHaveKey('hit_rate');
    expect($stats['total_hits'])->toBe(100);
    expect($stats['total_misses'])->toBe(20);
});

test('it cleans expired monitoring data', function () {
    Cache::shouldReceive('store')
        ->times(4)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->times(4)
        ->andReturn(true);

    $result = $this->optimizer->cleanExpiredMonitoringData();

    expect($result)->toBeTrue();
});

test('it updates user activity', function () {
    $customerId = 'user123';
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('put')
        ->once()
        ->with('cart:activity:user123', Mockery::type('int'), 86400)
        ->andReturn(true);

    $result = $this->optimizer->updateUserActivity($customerId);

    expect($result)->toBeTrue();
});

test('it handles cache exceptions gracefully', function () {
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);
    
    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andThrow(new \Exception('Cache connection failed'));
        
    Log::shouldReceive('error')
        ->once()
        ->with(Mockery::type('string'), Mockery::type('array'));

    $result = $this->optimizer->cacheCart($customerId, $cart);

    expect($result)->toBeFalse();
});

test('it uses custom cache store when configured', function () {
    $optimizer = new CartCacheOptimizer('redis');
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);
    
    Cache::shouldReceive('store')
        ->once()
        ->with('redis')
        ->andReturnSelf();
        
    Cache::shouldReceive('put')
        ->once()
        ->andReturn(true);

    $result = $optimizer->cacheCart($customerId, $cart);

    expect($result)->toBeTrue();
});