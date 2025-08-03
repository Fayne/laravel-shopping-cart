<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use MobileNowGroup\ShoppingCart\Storage\CacheCartStorage;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    
    $this->mockDatabaseStorage = Mockery::mock(DatabaseCartStorage::class);
    $this->storage = new CacheCartStorage($this->mockDatabaseStorage);
});

afterEach(function () {
    Mockery::close();
});

test('it can get cart from cache', function () {
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
        
    Cache::shouldReceive('remember')
        ->once()
        ->with("cart:cart:{$customerId}", 3600, Mockery::type('callable'))
        ->andReturn($cart);

    $result = $this->storage->getCart($customerId);

    expect($result)->toBe($cart);
});

test('it falls back to database when cache miss', function () {
    $customerId = 'user123';
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->mockDatabaseStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId)
        ->andReturn($cart);

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('remember')
        ->once()
        ->with("cart:cart:{$customerId}", 3600, Mockery::type('callable'))
        ->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

    $result = $this->storage->getCart($customerId);

    expect($result)->toBe($cart);
});

test('it can create cart and clear cache', function () {
    $customerId = 'user123';
    $data = ['customer_name' => 'John Doe'];
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->mockDatabaseStorage
        ->shouldReceive('createCart')
        ->once()
        ->with($customerId, $data)
        ->andReturn($cart);

    Cache::shouldReceive('store')
        ->times(3)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:cart:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:stats:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cart->id}");

    $result = $this->storage->createCart($customerId, $data);

    expect($result)->toBe($cart);
});

test('it can update cart and clear cache', function () {
    $customerId = 'user123';
    $data = ['customer_name' => 'Jane Doe'];
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'Jane Doe',
    ]);

    $this->mockDatabaseStorage
        ->shouldReceive('updateCart')
        ->once()
        ->with($customerId, $data)
        ->andReturn($cart);

    Cache::shouldReceive('store')
        ->times(3)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:cart:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:stats:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cart->id}");

    $result = $this->storage->updateCart($customerId, $data);

    expect($result)->toBe($cart);
});

test('it can delete cart and clear cache', function () {
    $customerId = 'user123';

    $this->mockDatabaseStorage
        ->shouldReceive('deleteCart')
        ->once()
        ->with($customerId)
        ->andReturn(true);

    Cache::shouldReceive('store')
        ->times(2)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:cart:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:stats:{$customerId}");

    $result = $this->storage->deleteCart($customerId);

    expect($result)->toBeTrue();
});

test('it can add item and clear cache', function () {
    $cartId = '1';
    $itemData = [
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ];
    $item = new ShoppingCartItem(array_merge($itemData, ['id' => 1, 'cart_id' => $cartId]));

    $this->mockDatabaseStorage
        ->shouldReceive('addItem')
        ->once()
        ->with($cartId, $itemData)
        ->andReturn($item);

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cartId}");

    $result = $this->storage->addItem($cartId, $itemData);

    expect($result)->toBe($item);
});

test('it can update items and clear cache', function () {
    $cartId = '1';
    $items = [['id' => 1, 'quantity' => 5]];

    $this->mockDatabaseStorage
        ->shouldReceive('updateItems')
        ->once()
        ->with($cartId, $items)
        ->andReturn(true);

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cartId}");

    $result = $this->storage->updateItems($cartId, $items);

    expect($result)->toBeTrue();
});

test('it can remove item and clear cache', function () {
    $cartId = '1';
    $itemId = 1;

    $this->mockDatabaseStorage
        ->shouldReceive('removeItem')
        ->once()
        ->with($cartId, $itemId)
        ->andReturn(true);

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cartId}");

    $result = $this->storage->removeItem($cartId, $itemId);

    expect($result)->toBeTrue();
});

test('it can empty cart and clear cache', function () {
    $customerId = 'user123';

    $this->mockDatabaseStorage
        ->shouldReceive('emptyCart')
        ->once()
        ->with($customerId)
        ->andReturn(true);

    Cache::shouldReceive('store')
        ->times(2)
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:cart:{$customerId}");
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:stats:{$customerId}");

    $result = $this->storage->emptyCart($customerId);

    expect($result)->toBeTrue();
});

test('it can toggle items selection and clear cache', function () {
    $cartId = '1';
    $itemIds = [1, 2];
    $selected = true;

    $this->mockDatabaseStorage
        ->shouldReceive('toggleItemsSelection')
        ->once()
        ->with($cartId, $itemIds, $selected)
        ->andReturn(true);

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('forget')
        ->once()
        ->with("cart:items:{$cartId}");

    $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);

    expect($result)->toBeTrue();
});

test('it can get items count from cache', function () {
    $customerId = 'user123';
    $count = 5;

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('remember')
        ->once()
        ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
        ->andReturn(['items_count' => $count]);

    $result = $this->storage->getItemsCount($customerId);

    expect($result)->toBe($count);
});

test('it can get items qty from cache', function () {
    $customerId = 'user123';
    $qty = 10;

    Cache::shouldReceive('store')
        ->once()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('remember')
        ->once()
        ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
        ->andReturn(['items_qty' => $qty]);

    $result = $this->storage->getItemsQty($customerId);

    expect($result)->toBe($qty);
});

test('it falls back to database for stats when cache miss', function () {
    $customerId = 'user123';
    $count = 3;
    $qty = 8;

    $this->mockDatabaseStorage
        ->shouldReceive('getItemsCount')
        ->once()
        ->with($customerId)
        ->andReturn($count);
        
    $this->mockDatabaseStorage
        ->shouldReceive('getItemsQty')
        ->once()
        ->with($customerId)
        ->andReturn($qty);

    Cache::shouldReceive('store')
        ->twice()
        ->with('default')
        ->andReturnSelf();
        
    Cache::shouldReceive('remember')
        ->twice()
        ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
        ->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });

    $countResult = $this->storage->getItemsCount($customerId);
    $qtyResult = $this->storage->getItemsQty($customerId);

    expect($countResult)->toBe($count)
        ->and($qtyResult)->toBe($qty);
});