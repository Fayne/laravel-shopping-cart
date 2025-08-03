<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use MobileNowGroup\ShoppingCart\Listeners\CartOperationListener;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->listener = new CartOperationListener();
});

afterEach(function () {
    Mockery::close();
});

test('it handles cart item added event', function () {
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
    expect(true)->toBeTrue();
});

test('it handles cart item removed event', function () {
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

    expect(true)->toBeTrue();
});

test('it handles cart items updated event', function () {
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

    expect(true)->toBeTrue();
});

test('it handles cart emptied event', function () {
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

    expect(true)->toBeTrue();
});

test('it handles cart totals calculated event', function () {
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

    expect(true)->toBeTrue();
});

test('it updates cart statistics', function () {
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

    expect(true)->toBeTrue();
});

test('it records user behavior', function () {
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

    expect(true)->toBeTrue();
});

test('it triggers recommendation update', function () {
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

    expect(true)->toBeTrue();
});

test('it checks inventory alerts', function () {
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

    expect(true)->toBeTrue();
});

test('it caches cart totals', function () {
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

    expect(true)->toBeTrue();
});

test('it checks coupon applicability', function () {
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

    expect(true)->toBeTrue();
});

test('it updates user preferences', function () {
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

    expect(true)->toBeTrue();
});

test('it handles job failure', function () {
    $exception = new \Exception('Test failure');

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Cart operation listener failed') &&
                   isset($context['error']) &&
                   $context['error'] === 'Test failure';
        });

    $this->listener->failed($exception);

    expect(true)->toBeTrue();
});

test('it handles cache failures gracefully', function () {
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

    expect(true)->toBeTrue();
});

test('it handles unknown event types', function () {
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

    expect(true)->toBeTrue();
});

test('it processes events asynchronously', function () {
    expect(class_implements(CartOperationListener::class))->toContain('Illuminate\\Contracts\\Queue\\ShouldQueue');
});

test('it has correct queue configuration', function () {
    expect($this->listener->queue)->toBe('default');
    expect($this->listener->tries)->toBe(3);
    expect($this->listener->timeout)->toBe(60);
});