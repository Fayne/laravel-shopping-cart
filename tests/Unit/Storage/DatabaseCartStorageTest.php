<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class DatabaseCartStorageTest extends TestCase
{
    use RefreshDatabase;

    protected DatabaseCartStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->storage = new DatabaseCartStorage();
    }

    /** @test */
    public function it_can_create_a_cart()
    {
        $customerId = 'user123';
        $data = [
            'customer_name' => 'John Doe',
            'shipping_method' => 'standard',
        ];

        $cart = $this->storage->createCart($customerId, $data);

        $this->assertInstanceOf(ShoppingCart::class, $cart);
        $this->assertEquals($customerId, $cart->customer_id);
        $this->assertEquals('John Doe', $cart->customer_name);
        $this->assertEquals('standard', $cart->shipping_method);
        $this->assertTrue($cart->is_active);
    }

    /** @test */
    public function it_can_get_existing_cart()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
            'is_active' => true,
        ]);

        $retrievedCart = $this->storage->getCart($customerId);

        $this->assertInstanceOf(ShoppingCart::class, $retrievedCart);
        $this->assertEquals($cart->id, $retrievedCart->id);
        $this->assertEquals($customerId, $retrievedCart->customer_id);
    }

    /** @test */
    public function it_returns_null_when_cart_not_found()
    {
        $cart = $this->storage->getCart('nonexistent');
        
        $this->assertNull($cart);
    }

    /** @test */
    public function it_can_update_cart()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
            'is_active' => true,
        ]);

        $updateData = [
            'customer_name' => 'Jane Doe',
            'shipping_method' => 'express',
        ];

        $updatedCart = $this->storage->updateCart($customerId, $updateData);

        $this->assertEquals('Jane Doe', $updatedCart->customer_name);
        $this->assertEquals('express', $updatedCart->shipping_method);
    }

    /** @test */
    public function it_can_delete_cart()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
            'is_active' => true,
        ]);

        $result = $this->storage->deleteCart($customerId);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
    }

    /** @test */
    public function it_can_add_item_to_cart()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'is_active' => true,
        ]);

        $itemData = [
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
        ];

        $item = $this->storage->addItem($cart->id, $itemData);

        $this->assertInstanceOf(ShoppingCartItem::class, $item);
        $this->assertEquals($cart->id, $item->cart_id);
        $this->assertEquals(1, $item->product_id);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(10.00, $item->price);
        $this->assertEquals('Test Product', $item->name);
    }

    /** @test */
    public function it_can_update_cart_items()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'is_active' => true,
        ]);

        $item = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $updates = [
            [
                'id' => $item->id,
                'quantity' => 5,
                'price' => 15.00,
            ]
        ];

        $result = $this->storage->updateItems($cart->id, $updates);

        $this->assertTrue($result);
        
        $item->refresh();
        $this->assertEquals(5, $item->quantity);
        $this->assertEquals(15.00, $item->price);
    }

    /** @test */
    public function it_can_remove_item_from_cart()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'is_active' => true,
        ]);

        $item = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $result = $this->storage->removeItem($cart->id, $item->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    /** @test */
    public function it_can_empty_cart()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'is_active' => true,
        ]);

        // 添加一些商品
        ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 2,
            'quantity' => 1,
            'price' => 20.00,
            'name' => 'Product 2',
        ]);

        $result = $this->storage->emptyCart($customerId);

        $this->assertTrue($result);
        $this->assertEquals(0, $cart->items()->count());
    }

    /** @test */
    public function it_can_toggle_items_selection()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'is_active' => true,
        ]);

        $item1 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
            'selected' => false,
        ]);

        $item2 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 2,
            'quantity' => 1,
            'price' => 20.00,
            'name' => 'Product 2',
            'selected' => false,
        ]);

        $result = $this->storage->toggleItemsSelection($cart->id, [$item1->id, $item2->id], true);

        $this->assertTrue($result);
        
        $item1->refresh();
        $item2->refresh();
        
        $this->assertTrue($item1->selected);
        $this->assertTrue($item2->selected);
    }

    /** @test */
    public function it_can_get_items_count()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'is_active' => true,
            'items_count' => 3,
        ]);

        $count = $this->storage->getItemsCount($customerId);

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_get_items_quantity()
    {
        $customerId = 'user123';
        $cart = ShoppingCart::create([
            'customer_id' => $customerId,
            'is_active' => true,
            'items_qty' => 10,
        ]);

        $qty = $this->storage->getItemsQty($customerId);

        $this->assertEquals(10, $qty);
    }

    /** @test */
    public function it_handles_database_transactions_properly()
    {
        $customerId = 'user123';
        
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $cart = $this->storage->createCart($customerId);

        $this->assertInstanceOf(ShoppingCart::class, $cart);
    }

    /** @test */
    public function it_returns_zero_for_nonexistent_customer_counts()
    {
        $count = $this->storage->getItemsCount('nonexistent');
        $qty = $this->storage->getItemsQty('nonexistent');

        $this->assertEquals(0, $count);
        $this->assertEquals(0, $qty);
    }
}