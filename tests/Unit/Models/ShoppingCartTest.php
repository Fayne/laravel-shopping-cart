<?php
use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Models\ShoppingCartItem;



test('it can create a shopping cart', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);

    expect($cart)->toBeInstanceOf(ShoppingCart::class)
        ->and($cart->customer_id)->toBe('user123')
        ->and($cart->customer_name)->toBe('John Doe');
    
    $this->assertDatabaseHas('shopping_carts', [
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
});

test('it has fillable attributes', function () {
    $cart = new ShoppingCart();
    $expectedFillable = [
        'customer_id',
        'customer_name',
        'session_id',
        'metadata',
        'coupon_code',
        'is_active',
        'is_temp',
        'items_count',
    ];

    expect($cart->getFillable())->toBe($expectedFillable);
});

// test('it casts metadata to array', function () {
//     $metadata = ['key' => 'value', 'number' => 123];
//     $cart = ShoppingCart::create([
//         'customer_id' => 'user123',
//         'metadata' => $metadata,
//     ]);
//
//     expect($cart->metadata)->toBeArray()
//         ->and($cart->metadata)->toBe($metadata);
// });

test('it has many items relationship', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    $item1 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'total' => 20.00,
        'name' => 'Product 1',
    ]);

    $item2 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 15.00,
        'total' => 45.00,
        'name' => 'Product 2',
    ]);

    expect($cart->items)->toHaveCount(2)
        ->and($cart->items->contains($item1))->toBeTrue()
        ->and($cart->items->contains($item2))->toBeTrue();
});

test('it calculates total amount', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'total' => 20.00,
        'name' => 'Product 1',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 20.00,
        'total' => 20.00,
        'name' => 'Product 2',
    ]);

    expect($cart->getTotalAmount())->toBe(40.00);
});

test('it calculates total items', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'total' => 20.00,
        'name' => 'Product 1',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 20.00,
        'total' => 60.00,
        'name' => 'Product 2',
    ]);

    expect($cart->getTotalItems())->toBe(5);
});

test('it counts unique products', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 20.00,
        'name' => 'Product 2',
    ]);

    expect($cart->getUniqueProductCount())->toBe(2);
});

test('it checks if cart is empty', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    expect($cart->isEmpty())->toBeTrue();

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    $cart->refresh();
    expect($cart->isEmpty())->toBeFalse();
});

test('it finds item by product id', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    $item = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    $foundItem = $cart->findItemByProductId(1);
    expect($foundItem)->not->toBeNull()
        ->and($foundItem->id)->toBe($item->id);

    $notFoundItem = $cart->findItemByProductId(999);
    expect($notFoundItem)->toBeNull();
});

test('it finds item by product id and options', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    $item1 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
        'additional' => ['color' => 'red'],
    ]);

    $item2 = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Product 1',
        'additional' => ['color' => 'blue'],
    ]);

    $foundItem1 = $cart->findItemByProductIdAndOptions(1, ['color' => 'red']);
    expect($foundItem1)->not->toBeNull()
        ->and($foundItem1->id)->toBe($item1->id);

    $foundItem2 = $cart->findItemByProductIdAndOptions(1, ['color' => 'blue']);
    expect($foundItem2)->not->toBeNull()
        ->and($foundItem2->id)->toBe($item2->id);

    $notFoundItem = $cart->findItemByProductIdAndOptions(1, ['color' => 'green']);
    expect($notFoundItem)->toBeNull();
});

test('it checks if product exists in cart', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    expect($cart->hasProduct(1))->toBeFalse();

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    $cart->refresh();
    expect($cart->hasProduct(1))->toBeTrue()
        ->and($cart->hasProduct(2))->toBeFalse();
});

test('it clears all items', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 20.00,
        'name' => 'Product 2',
    ]);

    expect($cart->items)->toHaveCount(2);

    $cart->clearItems();
    $cart->refresh();

    expect($cart->items)->toHaveCount(0)
        ->and($cart->isEmpty())->toBeTrue();
});

test('it converts to array', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);

    $item = ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Product 1',
    ]);

    $cartArray = $cart->toArray();

    expect($cartArray)->toBeArray()
        ->and($cartArray['customer_id'])->toBe('user123')
        ->and($cartArray['customer_name'])->toBe('John Doe')
        ->and($cartArray)->toHaveKey('items')
        ->and($cartArray['items'])->toHaveCount(1);
});

// test('it handles null metadata', function () {
//     $cart = ShoppingCart::create([
//         'customer_id' => 'user123',
//         'metadata' => null,
//     ]);
//
//     expect($cart->metadata)->toBeNull();
// });

// test('it handles empty metadata', function () {
//     $cart = ShoppingCart::create([
//         'customer_id' => 'user123',
//         'metadata' => [],
//     ]);
//
//     expect($cart->metadata)->toBeArray()
//         ->and($cart->metadata)->toBeEmpty();
// });

// test('it updates metadata', function () {
//     $cart = ShoppingCart::create([
//         'customer_id' => 'user123',
//         'metadata' => ['initial' => 'value'],
//     ]);
//
//     $cart->update([
//         'metadata' => ['updated' => 'value', 'new' => 'data'],
//     ]);
//
//     expect($cart->metadata)->toBe(['updated' => 'value', 'new' => 'data']);
// });

test('it calculates zero total for empty cart', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    expect($cart->getTotalAmount())->toBe(0.00)
        ->and($cart->getTotalItems())->toBe(0)
        ->and($cart->getUniqueProductCount())->toBe(0);
});

test('it handles decimal prices correctly', function () {
    $cart = ShoppingCart::create([
        'customer_id' => 'user123',
    ]);

    ShoppingCartItem::create([
        'cart_id' => $cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 9.99,
        'total' => 29.97,
        'name' => 'Product 1',
    ]);

    expect($cart->getTotalAmount())->toBe(29.97);
});