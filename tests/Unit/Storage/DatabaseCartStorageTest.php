<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    $this->storage = new DatabaseCartStorage();
});

test('it can create a cart', function () {
    $customerId = 'user123';
    $data = [
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ];

    $cart = $this->storage->createCart($data);

    expect($cart)->toBeInstanceOf(ShoppingCart::class)
        ->and($cart->customer_id)->toBe($customerId)
        ->and($cart->customer_name)->toBe('John Doe');
});

test('it can get existing cart', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
        'is_active' => true,
    ]);
    
    // 添加商品项，因为 getCart 使用了 withWhereHas
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $retrievedCart = $this->storage->getCart($customerId);

    expect($retrievedCart)->toBeInstanceOf(ShoppingCart::class)
        ->and($retrievedCart->id)->toBe($cart->id)
        ->and($retrievedCart->customer_id)->toBe($customerId);
});

test('it returns null when cart not found', function () {
    $cart = $this->storage->getCart('nonexistent');
    
    expect($cart)->toBeNull();
});

test('it can update cart', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
        'is_active' => true,
    ]);

    $cart->customer_name = 'Jane Doe';
    $cart->coupon_code = 'SAVE10';

    $result = $this->storage->updateCart($cart);

    expect($result)->toBeTrue();
    
    $cart->refresh();
    expect($cart->customer_name)->toBe('Jane Doe')
        ->and($cart->coupon_code)->toBe('SAVE10');
});

test('it can delete cart', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
        'is_active' => true,
    ]);

    $result = $this->storage->deleteCart($customerId);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
});

test('it can add item to cart', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'is_active' => true,
    ]);

    $itemData = [
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
        'sku' => 'TEST-001',
    ];

    $item = $this->storage->addItem($cart->id, $itemData);

    expect($item)->toBeInstanceOf(ShoppingCartItem::class)
        ->and($item->cart_id)->toBe($cart->id)
        ->and($item->itemable_id)->toBe(1)
        ->and($item->quantity)->toBe(2)
        ->and($item->price)->toBe('10.00')
        ->and($item->name)->toBe('Test Product');
});

test('it can update cart items', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'is_active' => true,
    ]);

    $item = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $result = $this->storage->updateCartItem($item->id, [
        'quantity' => 5,
        'price' => 15.00,
    ]);

    expect($result)->toBeTrue();
    
    $item->refresh();
    expect($item->quantity)->toBe(5)
        ->and($item->price)->toBe(15.00);
});

test('it can remove item from cart', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'is_active' => true,
    ]);

    $item = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $result = $this->storage->removeCartItem($item->id);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
});

test('it can empty cart', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'is_active' => true,
    ]);

    // 添加一些商品
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 2,
        'quantity' => 1,
        'price' => 20.00,
        'name' => 'Product 2',
    ]);

    $result = $this->storage->emptyCart($cart->id);

    expect($result)->toBeTrue()
        ->and($cart->items()->count())->toBe(0);
});

test('it can toggle items selection', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'is_active' => true,
    ]);

    $item1 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
        'selected' => false,
    ]);

    $item2 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 2,
        'quantity' => 1,
        'price' => 20.00,
        'name' => 'Product 2',
        'selected' => false,
    ]);

    $result = $this->storage->toggleAllItems($cart->id, true);

    expect($result)->toBeTrue();
    
    $item1->refresh();
    $item2->refresh();
    
    expect($item1->selected)->toBeTrue()
        ->and($item2->selected)->toBeTrue();
});

test('it can get items count', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'is_active' => true,
    ]);
    
    // 添加一些商品来测试数量统计
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);
    
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 2,
        'quantity' => 1,
        'price' => 20.00,
        'name' => 'Product 2',
    ]);
    
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 3,
        'quantity' => 1,
        'price' => 15.00,
        'name' => 'Product 3',
    ]);

    $stats = $this->storage->getCartStats($cart->id);

    expect($stats['items_count'])->toBe(3);
});

test('it can get items quantity', function () {
    $customerId = 'user123';
    $cart = ShoppingCart::create([
        'customer_id' => $customerId,
        'is_active' => true,
    ]);
    
    // 添加一些商品来测试数量统计
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 1,
        'quantity' => 5,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);
    
    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_type' => 'Product',
        'itemable_id' => 2,
        'quantity' => 3,
        'price' => 20.00,
        'name' => 'Product 2',
    ]);

    $stats = $this->storage->getCartStats($cart->id);

    expect($stats['items_qty'])->toBe(8);
});

test('it can handle database transactions', function () {
    $customerId = 'user123';

    $cart = $this->storage->createCart([
        'customer_id' => $customerId,
    ]);

    expect($cart)->toBeInstanceOf(ShoppingCart::class)
        ->and($cart->customer_id)->toBe($customerId);
});

test('it returns zero for nonexistent customer counts', function () {
    // 创建一个不存在商品的购物车
    $cart = ShoppingCart::create([
        'customer_id' => 'nonexistent',
        'is_active' => true,
    ]);
    
    $stats = $this->storage->getCartStats($cart->id);

    expect($stats['items_count'])->toBe(0)
        ->and($stats['items_qty'])->toBe(0);
});