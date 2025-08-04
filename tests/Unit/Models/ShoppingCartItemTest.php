<?php
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;



beforeEach(function () {
    $this->cart = ShoppingCart::create([
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
});

test('it can create a shopping cart item', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);

    expect($item)->toBeInstanceOf(ShoppingCartItem::class)
        ->and($item->cart_id)->toEqual($this->cart->id)
        ->and($item->itemable_id)->toEqual(1)
        ->and($item->quantity)->toEqual(2)
        ->and($item->price)->toEqual(10.00)
        ->and($item->name)->toEqual('Test Product');
    
    $this->assertDatabaseHas('shopping_cart_items', [
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
    ]);
});

test('it has fillable attributes', function () {
    $item = new ShoppingCartItem();
    $expectedFillable = [
        'cart_id',
        'itemable_id',
        'itemable_type',
        'quantity',
        'price',
        'name',
        'sku',
        'type',
        'weight',
        'total_weight',
        'base_total_weight',
        'base_price',
        'custom_price',
        'total',
        'base_total',
        'discount_percent',
        'discount_amount',
        'base_discount_amount',
        'additional',
        'selected',
        'coupon_code',
    ];

    expect($item->getFillable())->toEqual($expectedFillable);
});

test('it casts attributes correctly', function () {
    $options = ['color' => 'red', 'size' => 'large'];
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.50,
        'name' => 'Test Product',
        'additional' => $options,
        'selected' => true,
    ]);

    expect($item->additional)->toBeArray()
        ->and($item->additional)->toEqual($options)
        ->and($item->price)->toBeFloat()
        ->and($item->price)->toEqual(10.50)
        ->and($item->selected)->toBeBool()
        ->and($item->selected)->toBeTrue();
});

test('it belongs to cart', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 15.50,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(46.50);
});

test('it calculates subtotal with zero quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 0,
        'price' => 15.50,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(0.00);
});

test('it calculates subtotal with zero price', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 0.00,
        'name' => 'Free Product',
    ]);

    expect($item->getSubtotal())->toEqual(0.00);
});

test('it checks if item has options', function () {
    $itemWithOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => ['color' => 'red'],
    ]);

    $itemWithoutOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 2,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product 2',
        'additional' => null,
    ]);

    $itemWithEmptyOptions = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 3,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product 3',
        'additional' => [],
    ]);

    expect($itemWithOptions->hasOptions())->toBeTrue()
        ->and($itemWithoutOptions->hasOptions())->toBeFalse()
        ->and($itemWithEmptyOptions->hasOptions())->toBeFalse();
});

test('it gets option value', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => ['color' => 'red', 'size' => 'large'],
    ]);

    expect($item->getOption('color'))->toEqual('red')
        ->and($item->getOption('size'))->toEqual('large')
        ->and($item->getOption('material'))->toBeNull()
        ->and($item->getOption('material', 'default'))->toEqual('default');
});

test('it gets option value from item without options', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => null,
    ]);

    expect($item->getOption('color'))->toBeNull()
        ->and($item->getOption('color', 'default'))->toEqual('default');
});

test('it checks if item matches product and options', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => ['color' => 'red', 'size' => 'large'],
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => null,
    ]);

    expect($item->matches(1, null))->toBeTrue()
        ->and($item->matches(1, []))->toBeTrue()
        ->and($item->matches(1, ['color' => 'red']))->toBeFalse()
        ->and($item->matches(2, null))->toBeFalse();
});

test('it updates quantity', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'selected' => true,
    ]);

    $item->toggleSelection();
    expect($item->selected)->toBeFalse();

    $item->toggleSelection();
    expect($item->selected)->toBeTrue();
});

test('it sets selection status', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
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
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.50,
        'name' => 'Test Product',
        'additional' => ['color' => 'red'],
        'selected' => true,
    ]);

    $itemArray = $item->toArray();

    expect($itemArray)->toBeArray()
        ->and($itemArray['cart_id'])->toEqual($this->cart->id)
        ->and($itemArray['itemable_id'])->toEqual(1)
        ->and($itemArray['quantity'])->toEqual(2)
        ->and($itemArray['price'])->toEqual(10.50)
        ->and($itemArray['name'])->toEqual('Test Product')
        ->and($itemArray['additional'])->toEqual(['color' => 'red'])
        ->and($itemArray['selected'])->toBeTrue();
});

test('it handles null options gracefully', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => null,
    ]);

    expect($item->additional)->toBeNull()
        ->and($item->hasOptions())->toBeFalse()
        ->and($item->getOption('color'))->toBeNull()
        ->and($item->matches(1, null))->toBeTrue()
        ->and($item->matches(1, []))->toBeTrue();
});

test('it handles empty options array', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => [],
    ]);

    expect($item->additional)->toBeArray()
        ->and($item->additional)->toBeEmpty()
        ->and($item->hasOptions())->toBeFalse()
        ->and($item->getOption('color'))->toBeNull()
        ->and($item->matches(1, []))->toBeTrue()
        ->and($item->matches(1, null))->toBeTrue();
});

test('it calculates subtotal with decimal precision', function () {
    $item = ShoppingCartItem::create([
        'cart_id' => $this->cart->id,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 3,
        'price' => 9.99,
        'name' => 'Test Product',
    ]);

    expect($item->getSubtotal())->toEqual(29.97);
});