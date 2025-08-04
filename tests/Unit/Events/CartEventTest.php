<?php

use MobileNowGroup\ShoppingCart\Events\CartItemAdded;
use MobileNowGroup\ShoppingCart\Events\CartItemRemoved;
use MobileNowGroup\ShoppingCart\Events\CartItemUpdated;
use MobileNowGroup\ShoppingCart\Events\CartCleared;
use MobileNowGroup\ShoppingCart\Events\CartTotalCalculated;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

beforeEach(function () {
    $this->cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $this->cart->id = 1; // Explicitly set the id
    
    $this->item = new ShoppingCartItem([
        'id' => 1,
        'cart_id' => 1,
        'itemable_id' => 1,
        'itemable_type' => 'Product',
        'quantity' => 2,
        'price' => 10.00,
        'name' => 'Test Product',
        'additional' => ['color' => 'red'],
    ]);
    $this->item->cart_id = 1; // Explicitly set the cart_id
});

test('cart item added event contains correct data', function () {
    $event = new CartItemAdded($this->cart, $this->item);
    
    expect($event->cart)->toBe($this->cart)
        ->and($event->item)->toBe($this->item)
        ->and($event->cart->customer_id)->toBe('user123')
        ->and($event->item->product_id)->toBe(1)
        ->and($event->item->quantity)->toBe(2)
        ->and($event->item->price)->toBe('10.00');
});

test('cart item removed event contains correct data', function () {
    $event = new CartItemRemoved($this->cart, $this->item);
    
    expect($event->cart)->toBe($this->cart)
        ->and($event->item)->toBe($this->item)
        ->and($event->cart->customer_id)->toBe('user123')
        ->and($event->item->product_id)->toBe(1);
});

test('cart item updated event contains correct data', function () {
    $oldQuantity = 1;
    $event = new CartItemUpdated($this->cart, $this->item, $oldQuantity);
    
    expect($event->cart)->toBe($this->cart)
        ->and($event->item)->toBe($this->item)
        ->and($event->oldQuantity)->toBe($oldQuantity)
        ->and($event->cart->customer_id)->toBe('user123')
        ->and($event->item->quantity)->toBe(2)
        ->and($event->oldQuantity)->toBe(1);
});

test('cart cleared event contains correct data', function () {
    $items = collect([$this->item]);
    $event = new CartCleared($this->cart, $items);
    
    expect($event->cart)->toBe($this->cart)
        ->and($event->items)->toBe($items)
        ->and($event->cart->customer_id)->toBe('user123')
        ->and($event->items)->toHaveCount(1)
        ->and($event->items->first()->product_id)->toBe(1);
});

test('cart total calculated event contains correct data', function () {
    $total = 20.00;
    $itemCount = 2;
    $event = new CartTotalCalculated($this->cart, $total, $itemCount);
    
    expect($event->cart)->toBe($this->cart)
        ->and($event->total)->toBe(20.0)
        ->and($event->itemCount)->toBe(2)
        ->and($event->cart->customer_id)->toBe('user123');
});

test('events can be serialized', function () {
    $events = [
        new CartItemAdded($this->cart, $this->item),
        new CartItemRemoved($this->cart, $this->item),
        new CartItemUpdated($this->cart, $this->item, 1),
        new CartCleared($this->cart, collect([$this->item])),
        new CartTotalCalculated($this->cart, 20.00, 2),
    ];
    
    foreach ($events as $event) {
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);
        
        expect($unserialized->cart->customer_id)->toBe($event->cart->customer_id);
        
        if (property_exists($event, 'item')) {
            expect($unserialized->item->product_id)->toBe($event->item->product_id);
        }
    }
});

test('events handle null values gracefully', function () {
    $cartWithNulls = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => null,
    ]);
    
    $itemWithNulls = new ShoppingCartItem([
        'id' => 1,
        'cart_id' => 1,
        'product_id' => 1,
        'quantity' => 1,
        'price' => 10.00,
        'name' => 'Test Product',
        'options' => null,
    ]);
    
    $event = new CartItemAdded($cartWithNulls, $itemWithNulls);
    
    expect($event->cart->customer_name)->toBeNull()
        ->and($event->item->options)->toBeNull();
});

test('cart item updated event handles zero old quantity', function () {
    $event = new CartItemUpdated($this->cart, $this->item, 0);
    
    expect($event->oldQuantity)->toBe(0)
        ->and($event->item->quantity)->toBe(2);
});

test('cart cleared event handles empty items collection', function () {
    $emptyItems = collect([]);
    $event = new CartCleared($this->cart, $emptyItems);
    
    expect($event->items)->toHaveCount(0)
        ->and($event->items->isEmpty())->toBeTrue();
});

test('cart total calculated event handles zero values', function () {
    $event = new CartTotalCalculated($this->cart, 0.00, 0);
    
    expect($event->total)->toBe(0.0)
        ->and($event->itemCount)->toBe(0);
});

test('events preserve cart and item relationships', function () {
    $event = new CartItemAdded($this->cart, $this->item);
    
    expect($event->cart->id)->toBe(1)
        ->and($event->item->cart_id)->toBe(1);
});

test('events can access item options', function () {
    $event = new CartItemAdded($this->cart, $this->item);
    
    expect($event->item->options)->toBeArray()
        ->and($event->item->options['color'])->toBe('red');
});

test('cart item updated event calculates quantity difference', function () {
    $oldQuantity = 1;
    $event = new CartItemUpdated($this->cart, $this->item, $oldQuantity);
    
    $quantityDifference = $event->item->quantity - $event->oldQuantity;
    expect($quantityDifference)->toBe(1);
});

test('events maintain data integrity', function () {
    $events = [
        new CartItemAdded($this->cart, $this->item),
        new CartItemRemoved($this->cart, $this->item),
        new CartItemUpdated($this->cart, $this->item, 1),
        new CartCleared($this->cart, collect([$this->item])),
        new CartTotalCalculated($this->cart, 20.00, 2),
    ];
    
    foreach ($events as $event) {
        // Verify cart data integrity
        expect($event->cart)->not->toBeNull()
            ->and($event->cart->customer_id)->not->toBeNull();
        
        // Verify event-specific data integrity
        if ($event instanceof CartItemAdded || $event instanceof CartItemRemoved) {
            expect($event->item)->not->toBeNull()
                ->and($event->item->product_id)->not->toBeNull();
        }
        
        if ($event instanceof CartItemUpdated) {
            expect($event->item)->not->toBeNull()
                ->and($event->oldQuantity)->toBeNumeric();
        }
        
        if ($event instanceof CartCleared) {
            expect($event->items)->not->toBeNull();
        }
        
        if ($event instanceof CartTotalCalculated) {
            expect($event->total)->toBeNumeric()
                ->and($event->itemCount)->toBeNumeric();
        }
    }
});