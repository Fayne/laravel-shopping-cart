<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Events;

use MobileNowGroup\ShoppingCart\Events\CartItemAdded;
use MobileNowGroup\ShoppingCart\Events\CartItemRemoved;
use MobileNowGroup\ShoppingCart\Events\CartItemUpdated;
use MobileNowGroup\ShoppingCart\Events\CartCleared;
use MobileNowGroup\ShoppingCart\Events\CartTotalCalculated;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartEventTest extends TestCase
{
    protected $cart;
    protected $item;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);
        
        $this->item = new ShoppingCartItem([
            'id' => 1,
            'cart_id' => 1,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => ['color' => 'red'],
        ]);
    }

    /** @test */
    public function cart_item_added_event_contains_correct_data()
    {
        $event = new CartItemAdded($this->cart, $this->item);
        
        $this->assertSame($this->cart, $event->cart);
        $this->assertSame($this->item, $event->item);
        $this->assertEquals('user123', $event->cart->customer_id);
        $this->assertEquals(1, $event->item->product_id);
        $this->assertEquals(2, $event->item->quantity);
        $this->assertEquals(10.00, $event->item->price);
    }

    /** @test */
    public function cart_item_removed_event_contains_correct_data()
    {
        $event = new CartItemRemoved($this->cart, $this->item);
        
        $this->assertSame($this->cart, $event->cart);
        $this->assertSame($this->item, $event->item);
        $this->assertEquals('user123', $event->cart->customer_id);
        $this->assertEquals(1, $event->item->product_id);
    }

    /** @test */
    public function cart_item_updated_event_contains_correct_data()
    {
        $oldQuantity = 1;
        $event = new CartItemUpdated($this->cart, $this->item, $oldQuantity);
        
        $this->assertSame($this->cart, $event->cart);
        $this->assertSame($this->item, $event->item);
        $this->assertEquals($oldQuantity, $event->oldQuantity);
        $this->assertEquals('user123', $event->cart->customer_id);
        $this->assertEquals(2, $event->item->quantity);
        $this->assertEquals(1, $event->oldQuantity);
    }

    /** @test */
    public function cart_cleared_event_contains_correct_data()
    {
        $items = collect([$this->item]);
        $event = new CartCleared($this->cart, $items);
        
        $this->assertSame($this->cart, $event->cart);
        $this->assertSame($items, $event->items);
        $this->assertEquals('user123', $event->cart->customer_id);
        $this->assertCount(1, $event->items);
        $this->assertEquals(1, $event->items->first()->product_id);
    }

    /** @test */
    public function cart_total_calculated_event_contains_correct_data()
    {
        $total = 20.00;
        $itemCount = 2;
        $event = new CartTotalCalculated($this->cart, $total, $itemCount);
        
        $this->assertSame($this->cart, $event->cart);
        $this->assertEquals($total, $event->total);
        $this->assertEquals($itemCount, $event->itemCount);
        $this->assertEquals('user123', $event->cart->customer_id);
        $this->assertEquals(20.00, $event->total);
        $this->assertEquals(2, $event->itemCount);
    }

    /** @test */
    public function events_can_be_serialized()
    {
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
            
            $this->assertEquals($event->cart->customer_id, $unserialized->cart->customer_id);
            
            if (property_exists($event, 'item')) {
                $this->assertEquals($event->item->product_id, $unserialized->item->product_id);
            }
        }
    }

    /** @test */
    public function events_handle_null_values_gracefully()
    {
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
        
        $this->assertNull($event->cart->customer_name);
        $this->assertNull($event->item->options);
    }

    /** @test */
    public function cart_item_updated_event_handles_zero_old_quantity()
    {
        $event = new CartItemUpdated($this->cart, $this->item, 0);
        
        $this->assertEquals(0, $event->oldQuantity);
        $this->assertEquals(2, $event->item->quantity);
    }

    /** @test */
    public function cart_cleared_event_handles_empty_items_collection()
    {
        $emptyItems = collect([]);
        $event = new CartCleared($this->cart, $emptyItems);
        
        $this->assertCount(0, $event->items);
        $this->assertTrue($event->items->isEmpty());
    }

    /** @test */
    public function cart_total_calculated_event_handles_zero_values()
    {
        $event = new CartTotalCalculated($this->cart, 0.00, 0);
        
        $this->assertEquals(0.00, $event->total);
        $this->assertEquals(0, $event->itemCount);
    }

    /** @test */
    public function events_preserve_cart_and_item_relationships()
    {
        $event = new CartItemAdded($this->cart, $this->item);
        
        $this->assertEquals($event->cart->id, $event->item->cart_id);
    }

    /** @test */
    public function events_can_access_item_options()
    {
        $event = new CartItemAdded($this->cart, $this->item);
        
        $this->assertIsArray($event->item->options);
        $this->assertEquals('red', $event->item->options['color']);
    }

    /** @test */
    public function cart_item_updated_event_calculates_quantity_difference()
    {
        $oldQuantity = 1;
        $event = new CartItemUpdated($this->cart, $this->item, $oldQuantity);
        
        $quantityDifference = $event->item->quantity - $event->oldQuantity;
        $this->assertEquals(1, $quantityDifference);
    }

    /** @test */
    public function events_maintain_data_integrity()
    {
        $events = [
            new CartItemAdded($this->cart, $this->item),
            new CartItemRemoved($this->cart, $this->item),
            new CartItemUpdated($this->cart, $this->item, 1),
            new CartCleared($this->cart, collect([$this->item])),
            new CartTotalCalculated($this->cart, 20.00, 2),
        ];
        
        foreach ($events as $event) {
            // Verify cart data integrity
            $this->assertNotNull($event->cart);
            $this->assertNotNull($event->cart->customer_id);
            
            // Verify event-specific data integrity
            if ($event instanceof CartItemAdded || $event instanceof CartItemRemoved) {
                $this->assertNotNull($event->item);
                $this->assertNotNull($event->item->product_id);
            }
            
            if ($event instanceof CartItemUpdated) {
                $this->assertNotNull($event->item);
                $this->assertIsNumeric($event->oldQuantity);
            }
            
            if ($event instanceof CartCleared) {
                $this->assertNotNull($event->items);
            }
            
            if ($event instanceof CartTotalCalculated) {
                $this->assertIsNumeric($event->total);
                $this->assertIsNumeric($event->itemCount);
            }
        }
    }
}