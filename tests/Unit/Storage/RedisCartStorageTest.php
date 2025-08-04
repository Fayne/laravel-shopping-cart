<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use MobileNowGroup\ShoppingCart\Storage\RedisCartStorage;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockFallbackStorage = Mockery::mock(CartStorageInterface::class);
    $this->mockRedis = Mockery::mock();
    
    Redis::shouldReceive('connection')
        ->with('default')
        ->andReturn($this->mockRedis);
        
    $this->storage = new RedisCartStorage($this->mockFallbackStorage);
});

afterEach(function () {
    Mockery::close();
});

test('it can get cart from redis', function () {
    $customerId = 'user123';
    $cartData = [
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ];
    
    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:cart:{$customerId}")
        ->andReturn(json_encode($cartData));

    $result = $this->storage->getCart($customerId);

    expect($result)->toBeInstanceOf(ShoppingCart::class)
        ->and($result->customer_id)->toBe($customerId)
        ->and($result->customer_name)->toBe('John Doe');
});

test('it falls back to fallback storage when redis fails', function () {
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:cart:{$customerId}")
        ->andReturn(null);
        
    $this->mockFallbackStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId)
        ->andReturn($cart);
        
    $this->mockRedis
        ->shouldReceive('setex')
        ->once()
        ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));

    $result = $this->storage->getCart($customerId);

    expect($result)->toBe($cart);
});

test('it can create cart and cache in redis', function () {
    $customerId = 'user123';
    $data = ['customer_name' => 'John Doe'];
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->mockFallbackStorage
        ->shouldReceive('createCart')
        ->once()
        ->with($customerId, $data)
        ->andReturn($cart);
        
    $this->mockRedis
        ->shouldReceive('setex')
        ->once()
        ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:stats:{$customerId}", "cart:items:{$cart->id}"]);

    $result = $this->storage->createCart($customerId, $data);

    expect($result)->toBe($cart);
});

test('it can update cart and update redis cache', function () {
    $customerId = 'user123';
    $data = ['customer_name' => 'Jane Doe'];
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'Jane Doe',
    ]);

    $this->mockFallbackStorage
        ->shouldReceive('updateCart')
        ->once()
        ->with($customerId, $data)
        ->andReturn($cart);
        
    $this->mockRedis
        ->shouldReceive('setex')
        ->once()
        ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:stats:{$customerId}", "cart:items:{$cart->id}"]);

    $result = $this->storage->updateCart($customerId, $data);

    expect($result)->toBe($cart);
});

test('it can delete cart and clear redis cache', function () {
    $customerId = 'user123';

    $this->mockFallbackStorage
        ->shouldReceive('deleteCart')
        ->once()
        ->with($customerId)
        ->andReturn(true);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:cart:{$customerId}", "cart:stats:{$customerId}"]);

    $result = $this->storage->deleteCart($customerId);

    expect($result)->toBeTrue();
});

test('it can add item and clear redis cache', function () {
    $cartId = '1';
    $itemData = [
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ];
    $item = new ShoppingCartItem(array_merge($itemData, ['id' => 1, 'cart_id' => $cartId]));

    $this->mockFallbackStorage
        ->shouldReceive('addItem')
        ->once()
        ->with($cartId, $itemData)
        ->andReturn($item);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:items:{$cartId}"]);

    $result = $this->storage->addItem($cartId, $itemData);

    expect($result)->toBe($item);
});

test('it can update items and clear redis cache', function () {
    $cartId = '1';
    $items = [['id' => 1, 'quantity' => 5]];

    $this->mockFallbackStorage
        ->shouldReceive('updateItems')
        ->once()
        ->with($cartId, $items)
        ->andReturn(true);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:items:{$cartId}"]);

    $result = $this->storage->updateItems($cartId, $items);

    expect($result)->toBeTrue();
});

test('it can remove item and clear redis cache', function () {
    $cartId = '1';
    $itemId = 1;

    $this->mockFallbackStorage
        ->shouldReceive('removeItem')
        ->once()
        ->with($cartId, $itemId)
        ->andReturn(true);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:items:{$cartId}"]);

    $result = $this->storage->removeItem($cartId, $itemId);

    expect($result)->toBeTrue();
});

test('it can empty cart and clear redis cache', function () {
    $customerId = 'user123';

    $this->mockFallbackStorage
        ->shouldReceive('emptyCart')
        ->once()
        ->with($customerId)
        ->andReturn(true);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:cart:{$customerId}", "cart:stats:{$customerId}"]);

    $result = $this->storage->emptyCart($customerId);

    expect($result)->toBeTrue();
});

test('it can toggle items selection and clear redis cache', function () {
    $cartId = '1';
    $itemIds = [1, 2];
    $selected = true;

    $this->mockFallbackStorage
        ->shouldReceive('toggleItemsSelection')
        ->once()
        ->with($cartId, $itemIds, $selected)
        ->andReturn(true);
        
    $this->mockRedis
        ->shouldReceive('del')
        ->once()
        ->with(["cart:items:{$cartId}"]);

    $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);

    expect($result)->toBeTrue();
});

test('it can get items count from redis', function () {
    $customerId = 'user123';
    $statsData = ['items_count' => 5, 'items_qty' => 10];
    
    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:stats:{$customerId}")
        ->andReturn(json_encode($statsData));

    $result = $this->storage->getItemsCount($customerId);

    expect($result)->toBe(5);
});

test('it can get items qty from redis', function () {
    $customerId = 'user123';
    $statsData = ['items_count' => 5, 'items_qty' => 10];
    
    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:stats:{$customerId}")
        ->andReturn(json_encode($statsData));

    $result = $this->storage->getItemsQty($customerId);

    expect($result)->toBe(10);
});

test('it falls back to fallback storage for stats when redis fails', function () {
    $customerId = 'user123';
    $count = 3;
    $qty = 8;

    $this->mockRedis
        ->shouldReceive('get')
        ->twice()
        ->with("cart:stats:{$customerId}")
        ->andReturn(null);
        
    $this->mockFallbackStorage
        ->shouldReceive('getItemsCount')
        ->once()
        ->with($customerId)
        ->andReturn($count);
        
    $this->mockFallbackStorage
        ->shouldReceive('getItemsQty')
        ->once()
        ->with($customerId)
        ->andReturn($qty);
        
    $this->mockRedis
        ->shouldReceive('setex')
        ->twice()
        ->with("cart:stats:{$customerId}", 1800, json_encode(['items_count' => $count, 'items_qty' => $qty]));

    $countResult = $this->storage->getItemsCount($customerId);
    $qtyResult = $this->storage->getItemsQty($customerId);

    expect($countResult)->toBe($count)
        ->and($qtyResult)->toBe($qty);
});

test('it handles redis exceptions gracefully', function () {
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:cart:{$customerId}")
        ->andThrow(new \Exception('Redis connection failed'));
        
    $this->mockFallbackStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId)
        ->andReturn($cart);

    $result = $this->storage->getCart($customerId);

    expect($result)->toBe($cart);
});

test('it can get cart items from redis', function () {
    $cartId = '1';
    $itemsData = [
        [
            'id' => 1,
            'cart_id' => $cartId,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product 1',
        ],
        [
            'id' => 2,
            'cart_id' => $cartId,
            'product_id' => 2,
            'quantity' => 1,
            'price' => 20.00,
            'name' => 'Test Product 2',
        ],
    ];
    
    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:items:{$cartId}")
        ->andReturn(json_encode($itemsData));

    $result = $this->storage->getCartItems($cartId);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ShoppingCartItem::class)
        ->and($result[0]->name)->toBe('Test Product 1');
});

test('it falls back to fallback storage for items when redis fails', function () {
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

    $this->mockRedis
        ->shouldReceive('get')
        ->once()
        ->with("cart:items:{$cartId}")
        ->andReturn(null);
        
    $this->mockFallbackStorage
        ->shouldReceive('getCartItems')
        ->once()
        ->with($cartId)
        ->andReturn($items);
        
    $this->mockRedis
        ->shouldReceive('setex')
        ->once()
        ->with("cart:items:{$cartId}", 3600, json_encode($items->toArray()));

    $result = $this->storage->getCartItems($cartId);

    expect($result)->toBe($items);
});