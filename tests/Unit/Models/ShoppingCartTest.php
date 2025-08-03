<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class ShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_shopping_cart()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $this->assertInstanceOf(ShoppingCart::class, $cart);
        $this->assertEquals('user123', $cart->customer_id);
        $this->assertEquals('John Doe', $cart->customer_name);
        $this->assertDatabaseHas('shopping_carts', [
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $cart = new ShoppingCart();
        $expectedFillable = [
            'customer_id',
            'customer_name',
            'customer_email',
            'session_id',
            'metadata',
        ];

        $this->assertEquals($expectedFillable, $cart->getFillable());
    }

    /** @test */
    public function it_casts_metadata_to_array()
    {
        $metadata = ['key' => 'value', 'number' => 123];
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'metadata' => $metadata,
        ]);

        $this->assertIsArray($cart->metadata);
        $this->assertEquals($metadata, $cart->metadata);
    }

    /** @test */
    public function it_has_many_items_relationship()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $item1 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        $item2 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 2,
            'quantity' => 1,
            'price' => 20.00,
            'name' => 'Product 2',
        ]);

        $this->assertCount(2, $cart->items);
        $this->assertTrue($cart->items->contains($item1));
        $this->assertTrue($cart->items->contains($item2));
    }

    /** @test */
    public function it_calculates_total_amount()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

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

        $this->assertEquals(40.00, $cart->getTotalAmount());
    }

    /** @test */
    public function it_calculates_total_items()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

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
            'quantity' => 3,
            'price' => 20.00,
            'name' => 'Product 2',
        ]);

        $this->assertEquals(5, $cart->getTotalItems());
    }

    /** @test */
    public function it_counts_unique_products()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

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
            'quantity' => 3,
            'price' => 20.00,
            'name' => 'Product 2',
        ]);

        $this->assertEquals(2, $cart->getUniqueProductCount());
    }

    /** @test */
    public function it_checks_if_cart_is_empty()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $this->assertTrue($cart->isEmpty());

        ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        $cart->refresh();
        $this->assertFalse($cart->isEmpty());
    }

    /** @test */
    public function it_finds_item_by_product_id()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $item = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        $foundItem = $cart->findItemByProductId(1);
        $this->assertNotNull($foundItem);
        $this->assertEquals($item->id, $foundItem->id);

        $notFoundItem = $cart->findItemByProductId(999);
        $this->assertNull($notFoundItem);
    }

    /** @test */
    public function it_finds_item_by_product_id_and_options()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $item1 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
            'options' => ['color' => 'red'],
        ]);

        $item2 = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Product 1',
            'options' => ['color' => 'blue'],
        ]);

        $foundItem1 = $cart->findItemByProductIdAndOptions(1, ['color' => 'red']);
        $this->assertNotNull($foundItem1);
        $this->assertEquals($item1->id, $foundItem1->id);

        $foundItem2 = $cart->findItemByProductIdAndOptions(1, ['color' => 'blue']);
        $this->assertNotNull($foundItem2);
        $this->assertEquals($item2->id, $foundItem2->id);

        $notFoundItem = $cart->findItemByProductIdAndOptions(1, ['color' => 'green']);
        $this->assertNull($notFoundItem);
    }

    /** @test */
    public function it_checks_if_product_exists_in_cart()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $this->assertFalse($cart->hasProduct(1));

        ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        $cart->refresh();
        $this->assertTrue($cart->hasProduct(1));
        $this->assertFalse($cart->hasProduct(2));
    }

    /** @test */
    public function it_clears_all_items()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

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

        $this->assertCount(2, $cart->items);

        $cart->clearItems();
        $cart->refresh();

        $this->assertCount(0, $cart->items);
        $this->assertTrue($cart->isEmpty());
    }

    /** @test */
    public function it_converts_to_array()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
            'metadata' => ['key' => 'value'],
        ]);

        $item = ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Product 1',
        ]);

        $cartArray = $cart->toArray();

        $this->assertIsArray($cartArray);
        $this->assertEquals('user123', $cartArray['customer_id']);
        $this->assertEquals('John Doe', $cartArray['customer_name']);
        $this->assertArrayHasKey('items', $cartArray);
        $this->assertCount(1, $cartArray['items']);
    }

    /** @test */
    public function it_handles_null_metadata()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'metadata' => null,
        ]);

        $this->assertNull($cart->metadata);
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'metadata' => [],
        ]);

        $this->assertIsArray($cart->metadata);
        $this->assertEmpty($cart->metadata);
    }

    /** @test */
    public function it_updates_metadata()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'metadata' => ['initial' => 'value'],
        ]);

        $cart->update([
            'metadata' => ['updated' => 'value', 'new' => 'data'],
        ]);

        $this->assertEquals(['updated' => 'value', 'new' => 'data'], $cart->metadata);
    }

    /** @test */
    public function it_calculates_zero_total_for_empty_cart()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        $this->assertEquals(0.00, $cart->getTotalAmount());
        $this->assertEquals(0, $cart->getTotalItems());
        $this->assertEquals(0, $cart->getUniqueProductCount());
    }

    /** @test */
    public function it_handles_decimal_prices_correctly()
    {
        $cart = ShoppingCart::create([
            'customer_id' => 'user123',
        ]);

        ShoppingCartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 3,
            'price' => 9.99,
            'name' => 'Product 1',
        ]);

        $this->assertEquals(29.97, $cart->getTotalAmount());
    }
}