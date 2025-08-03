<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Optimization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartCacheOptimizerTest extends TestCase
{
    use RefreshDatabase;

    protected CartCacheOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->optimizer = new CartCacheOptimizer();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_correct_cache_keys()
    {
        $customerId = 'user123';
        $cartId = '1';
        
        $cartKey = $this->optimizer->getCacheKey('cart', $customerId);
        $itemsKey = $this->optimizer->getCacheKey('items', $cartId);
        $statsKey = $this->optimizer->getCacheKey('stats', $customerId);
        
        $this->assertEquals('cart:cart:user123', $cartKey);
        $this->assertEquals('cart:items:1', $itemsKey);
        $this->assertEquals('cart:stats:user123', $statsKey);
    }

    /** @test */
    public function it_can_cache_cart_data()
    {
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

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_cache_cart_items()
    {
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

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_cache_cart_stats()
    {
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

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_preload_cart_data()
    {
        $customerIds = ['user123', 'user456'];
        
        Cache::shouldReceive('store')
            ->times(6) // 2 customers * 3 cache operations each
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->times(6)
            ->andReturn(null); // Simulate cache miss

        $result = $this->optimizer->preloadCartData($customerIds);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('preloaded', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    /** @test */
    public function it_can_clear_cart_cache()
    {
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

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_bulk_clear_cache()
    {
        $customerIds = ['user123', 'user456'];
        
        Cache::shouldReceive('store')
            ->times(4) // 2 customers * 2 cache operations each (cart + stats)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->times(4)
            ->andReturn(true);

        $result = $this->optimizer->bulkClearCache($customerIds);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cleared', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    /** @test */
    public function it_calculates_dynamic_ttl_based_on_activity()
    {
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

        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
    }

    /** @test */
    public function it_calculates_default_ttl_for_inactive_users()
    {
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

        $this->assertEquals(3600, $ttl); // Default TTL
    }

    /** @test */
    public function it_records_cache_hit()
    {
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
        $this->assertTrue(true);
    }

    /** @test */
    public function it_records_cache_miss()
    {
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
        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_cache_statistics()
    {
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

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_hits', $stats);
        $this->assertArrayHasKey('total_misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertEquals(100, $stats['total_hits']);
        $this->assertEquals(20, $stats['total_misses']);
    }

    /** @test */
    public function it_cleans_expired_monitoring_data()
    {
        Cache::shouldReceive('store')
            ->times(4)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->times(4)
            ->andReturn(true);

        $result = $this->optimizer->cleanExpiredMonitoringData();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_updates_user_activity()
    {
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

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_cache_exceptions_gracefully()
    {
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

        $this->assertFalse($result);
    }

    /** @test */
    public function it_uses_custom_cache_store_when_configured()
    {
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

        $this->assertTrue($result);
    }
}