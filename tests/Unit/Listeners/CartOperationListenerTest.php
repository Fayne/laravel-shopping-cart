<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Listeners;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use MobileNowGroup\ShoppingCart\Listeners\CartOperationListener;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartOperationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected CartOperationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->listener = new CartOperationListener();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_cart_item_added_event()
    {
        $eventData = [
            'customer_id' => 'user123',
            'cart_id' => '1',
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ];

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('increment')
            ->once()
            ->with('cart:stats:user123:items_added')
            ->andReturn(1);
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:behavior:user123', Mockery::type('array'), 86400)
            ->andReturn(true);
            
        Cache::shouldReceive('forget')
            ->once()
            ->with('cart:totals:1')
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Cart item added', $eventData);

        $this->listener->handle((object) array_merge($eventData, ['type' => 'item_added']));

        // No assertion needed as we're testing the method doesn't throw
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cart_item_removed_event()
    {
        $eventData = [
            'customer_id' => 'user123',
            'cart_id' => '1',
            'item_id' => 1,
        ];

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('increment')
            ->once()
            ->with('cart:stats:user123:items_removed')
            ->andReturn(1);
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:behavior:user123', Mockery::type('array'), 86400)
            ->andReturn(true);
            
        Cache::shouldReceive('forget')
            ->once()
            ->with('cart:totals:1')
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Cart item removed', $eventData);

        $this->listener->handle((object) array_merge($eventData, ['type' => 'item_removed']));

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cart_items_updated_event()
    {
        $eventData = [
            'customer_id' => 'user123',
            'cart_id' => '1',
            'items' => [['id' => 1, 'quantity' => 5]],
        ];

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('increment')
            ->once()
            ->with('cart:stats:user123:items_updated')
            ->andReturn(1);
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:behavior:user123', Mockery::type('array'), 86400)
            ->andReturn(true);
            
        Cache::shouldReceive('forget')
            ->once()
            ->with('cart:totals:1')
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Cart items updated', $eventData);

        $this->listener->handle((object) array_merge($eventData, ['type' => 'items_updated']));

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cart_emptied_event()
    {
        $eventData = [
            'customer_id' => 'user123',
            'cart_id' => '1',
        ];

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('increment')
            ->once()
            ->with('cart:stats:user123:carts_emptied')
            ->andReturn(1);
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:behavior:user123', Mockery::type('array'), 86400)
            ->andReturn(true);
            
        Cache::shouldReceive('forget')
            ->once()
            ->with('cart:totals:1')
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Cart emptied', $eventData);

        $this->listener->handle((object) array_merge($eventData, ['type' => 'cart_emptied']));

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cart_totals_calculated_event()
    {
        $eventData = [
            'customer_id' => 'user123',
            'cart_id' => '1',
            'total' => 50.00,
            'items_count' => 3,
            'items_qty' => 5,
        ];

        Cache::shouldReceive('store')
            ->times(2)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:totals:1', Mockery::type('array'), 1800)
            ->andReturn(true);
            
        Cache::shouldReceive('put')
            ->once()
            ->with('cart:behavior:user123', Mockery::type('array'), 86400)
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Cart totals calculated', $eventData);

        $this->listener->handle((object) array_merge($eventData, ['type' => 'totals_calculated']));

        $this->assertTrue(true);
    }

    /** @test */
    public function it_updates_cart_statistics()
    {
        $customerId = 'user123';
        $operation = 'items_added';

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('increment')
            ->once()
            ->with("cart:stats:{$customerId}:{$operation}")
            ->andReturn(1);

        $this->listener->updateCartStatistics($customerId, $operation);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_records_user_behavior()
    {
        $customerId = 'user123';
        $action = 'add_item';
        $data = ['product_id' => 1, 'quantity' => 2];

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $behaviorData, $ttl) use ($customerId, $action, $data) {
                return $key === "cart:behavior:{$customerId}" &&
                       is_array($behaviorData) &&
                       isset($behaviorData['actions']) &&
                       is_array($behaviorData['actions']) &&
                       $ttl === 86400;
            })
            ->andReturn(true);

        $this->listener->recordUserBehavior($customerId, $action, $data);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_triggers_recommendation_update()
    {
        $customerId = 'user123';
        $productId = 1;

        Event::shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($eventName, $eventData) use ($customerId, $productId) {
                return $eventName === 'cart.recommendation.update' &&
                       $eventData['customer_id'] === $customerId &&
                       $eventData['product_id'] === $productId;
            });

        $this->listener->triggerRecommendationUpdate($customerId, $productId);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_checks_inventory_alerts()
    {
        $productId = 1;
        $quantity = 5;

        // Mock inventory check (in real implementation, this would check actual inventory)
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($productId, $quantity) {
                return str_contains($message, 'Low inventory alert') &&
                       $context['product_id'] === $productId &&
                       $context['requested_quantity'] === $quantity;
            });

        $this->listener->checkInventoryAlerts($productId, $quantity);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_caches_cart_totals()
    {
        $cartId = '1';
        $totals = [
            'total' => 50.00,
            'items_count' => 3,
            'items_qty' => 5,
            'calculated_at' => now()->toISOString(),
        ];

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->with("cart:totals:{$cartId}", $totals, 1800)
            ->andReturn(true);

        $this->listener->cacheCartTotals($cartId, $totals);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_checks_coupon_applicability()
    {
        $customerId = 'user123';
        $cartTotal = 100.00;

        // Mock coupon check (in real implementation, this would check actual coupons)
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($customerId, $cartTotal) {
                return str_contains($message, 'Checking coupon applicability') &&
                       $context['customer_id'] === $customerId &&
                       $context['cart_total'] === $cartTotal;
            });

        $this->listener->checkCouponApplicability($customerId, $cartTotal);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_updates_user_preferences()
    {
        $customerId = 'user123';
        $productId = 1;
        $action = 'add_to_cart';

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $preferences, $ttl) use ($customerId) {
                return $key === "cart:preferences:{$customerId}" &&
                       is_array($preferences) &&
                       isset($preferences['products']) &&
                       isset($preferences['categories']) &&
                       isset($preferences['updated_at']) &&
                       $ttl === 604800; // 7 days
            })
            ->andReturn(true);

        $this->listener->updateUserPreferences($customerId, $productId, $action);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_job_failure()
    {
        $exception = new \Exception('Test failure');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Cart operation listener failed') &&
                       isset($context['error']) &&
                       $context['error'] === 'Test failure';
            });

        $this->listener->failed($exception);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cache_failures_gracefully()
    {
        $customerId = 'user123';
        $operation = 'items_added';

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andThrow(new \Exception('Cache connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to update cart statistics') &&
                       isset($context['customer_id']) &&
                       isset($context['operation']);
            });

        $this->listener->updateCartStatistics($customerId, $operation);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_unknown_event_types()
    {
        $eventData = [
            'customer_id' => 'user123',
            'type' => 'unknown_event',
        ];

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Unknown cart operation event type') &&
                       $context['type'] === 'unknown_event';
            });

        $this->listener->handle((object) $eventData);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_processes_events_asynchronously()
    {
        $this->assertContains('Illuminate\Contracts\Queue\ShouldQueue', class_implements(CartOperationListener::class));
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $this->assertEquals('default', $this->listener->queue);
        $this->assertEquals(3, $this->listener->tries);
        $this->assertEquals(60, $this->listener->timeout);
    }
}