<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
});

test('it can create a shopping cart item', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    expect($item)->toBeInstanceOf(ShoppingCartItem::class)
        ->and($item->cart_id)->toEqual($this->cart->id)
        ->and($item->product_id)->toEqual(1)
        ->and($item->quantity)->toEqual(2)
        ->and($item->price)->toEqual(10.00)
        ->and($item->name)->toEqual('Test Product');
    
    $this->assertDatabaseHas('shopping_cart_items', [
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);
});

test('it has fillable attributes', function () {
    $item = new ShoppingCartItem();
    $expectedFillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'name',
        'description',
        'image',
        'options',
        'is_selected',
    ];

    expect($item->getFillable())->toEqual($expectedFillable);
});

test('it casts attributes correctly', function () {
    $options = ['color' => 'red', 'size' => 'large'];
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.50,
        'name' => 'Test Product',
        'options' => $options,
        'is_selected' => true,
    ]);

    expect($item->options)->toBeArray()
        ->and($item->options)->toEqual($options)
        ->and($item->price)->toBeFloat()
        ->and($item->price)->toEqual(10.50)
        ->and($item->is_selected)->toBeBool()
        ->and($item->is_selected)->toBeTrue();
});

test('it belongs to cart', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    expect($item->cart)->toBeInstanceOf(ShoppingCart::class)
        ->and($item->cart->id)->toEqual($this->cart->id)
        ->and($item->cart->customer_id)->toEqual('user123');
});

test('it calculates subtotal', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 3,
        'price' => 15.50,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(46.50);
});

test('it calculates subtotal with zero quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 0,
        'price' => 15.50,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(0.00);
});

test('it calculates subtotal with zero price', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 3,
        'price' => 0.00,
        'name' => 'Free Product',
    ]);

    expect($item->getSubtotal())->toEqual(0.00);
});

test('it checks if item has options', function () {
    $itemWithOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => ['color' => 'red'],
    ]);

    $itemWithoutOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 2,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product 2',
        'options' => null,
    ]);

    $itemWithEmptyOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 3,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product 3',
        'options' => [],
    ]);

    expect($itemWithOptions->hasOptions())->toBeTrue()
        ->and($itemWithoutOptions->hasOptions())->toBeFalse()
        ->and($itemWithEmptyOptions->hasOptions())->toBeFalse();
});

test('it gets option value', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => ['color' => 'red', 'size' => 'large'],
    ]);

    expect($item->getOption('color'))->toEqual('red')
        ->and($item->getOption('size'))->toEqual('large')
        ->and($item->getOption('material'))->toBeNull()
        ->and($item->getOption('material', 'default'))->toEqual('default');
});

test('it gets option value from item without options', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => null,
    ]);

    expect($item->getOption('color'))->toBeNull()
        ->and($item->getOption('color', 'default'))->toEqual('default');
});

test('it checks if item matches product and options', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => ['color' => 'red', 'size' => 'large'],
    ]);

    expect($item->matches(1, ['color' => 'red', 'size' => 'large']))->toBeTrue()
        ->and($item->matches(1, ['size' => 'large', 'color' => 'red']))->toBeTrue() // Order doesn't matter
        ->and($item->matches(1, ['color' => 'blue', 'size' => 'large']))->toBeFalse()
        ->and($item->matches(2, ['color' => 'red', 'size' => 'large']))->toBeFalse()
        ->and($item->matches(1, ['color' => 'red']))->toBeFalse()
        ->and($item->matches(1, ['color' => 'red', 'size' => 'large', 'material' => 'cotton']))->toBeFalse();
});

test('it matches item without options', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => null,
    ]);

    expect($item->matches(1, null))->toBeTrue()
        ->and($item->matches(1, []))->toBeTrue()
        ->and($item->matches(1, ['color' => 'red']))->toBeFalse()
        ->and($item->matches(2, null))->toBeFalse();
});

test('it updates quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $item->updateQuantity(5);

    expect($item->quantity)->toEqual(5);
    $this->assertDatabaseHas('shopping_cart_items', [
        'id' => $item->id,
        'quantity' => 5,
    ]);
});

test('it increments quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $item->incrementQuantity(3);

    expect($item->quantity)->toEqual(5);
    $this->assertDatabaseHas('shopping_cart_items', [
        'id' => $item->id,
        'quantity' => 5,
    ]);
});

test('it decrements quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 5,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $item->decrementQuantity(2);

    expect($item->quantity)->toEqual(3);
    $this->assertDatabaseHas('shopping_cart_items', [
        'id' => $item->id,
        'quantity' => 3,
    ]);
});

test('it prevents negative quantity when decrementing', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    $item->decrementQuantity(5);

    expect($item->quantity)->toEqual(0);
    $this->assertDatabaseHas('shopping_cart_items', [
        'id' => $item->id,
        'quantity' => 0,
    ]);
});

test('it toggles selection status', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'is_selected' => true,
    ]);

    $item->toggleSelection();
    expect($item->is_selected)->toBeFalse();

    $item->toggleSelection();
    expect($item->is_selected)->toBeTrue();
});

test('it sets selection status', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'is_selected' => false,
    ]);

    $item->setSelected(true);
    expect($item->is_selected)->toBeTrue();

    $item->setSelected(false);
    expect($item->is_selected)->toBeFalse();
});

test('it converts to array', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 2,
        'price' => 10.50,
        'name' => 'Test Product',
        'description' => 'A test product',
        'options' => ['color' => 'red'],
        'is_selected' => true,
    ]);

    $itemArray = $item->toArray();

    expect($itemArray)->toBeArray()
        ->and($itemArray['cart_id'])->toEqual($this->cart->id)
        ->and($itemArray['product_id'])->toEqual(1)
        ->and($itemArray['quantity'])->toEqual(2)
        ->and($itemArray['price'])->toEqual(10.50)
        ->and($itemArray['name'])->toEqual('Test Product')
        ->and($itemArray['description'])->toEqual('A test product')
        ->and($itemArray['options'])->toEqual(['color' => 'red'])
        ->and($itemArray['is_selected'])->toBeTrue();
});

test('it handles null options gracefully', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => null,
    ]);

    expect($item->options)->toBeNull()
        ->and($item->hasOptions())->toBeFalse()
        ->and($item->getOption('color'))->toBeNull()
        ->and($item->matches(1, null))->toBeTrue()
        ->and($item->matches(1, []))->toBeTrue();
});

test('it handles empty options array', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => [],
    ]);

    expect($item->options)->toBeArray()
        ->and($item->options)->toBeEmpty()
        ->and($item->hasOptions())->toBeFalse()
        ->and($item->getOption('color'))->toBeNull()
        ->and($item->matches(1, []))->toBeTrue()
        ->and($item->matches(1, null))->toBeTrue();
});

test('it calculates subtotal with decimal precision', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'product_id' => 1,
        'quantity' => 3,
        'price' => 9.99,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(29.97);
});