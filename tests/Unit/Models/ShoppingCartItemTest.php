<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class ShoppingCartItemTest extends TestCase
{
    use RefreshDatabase;

    protected $cart;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cart = ShoppingCart::create([
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);
    }

    /** @test */
    public function it_can_create_a_shopping_cart_item()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $this->assertInstanceOf(ShoppingCartItem::class, $item);
        $this->assertEquals($this->cart->id, $item->cart_id);
        $this->assertEquals(1, $item->product_id);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(10.00, $item->price);
        $this->assertEquals('Test Product', $item->name);
        
        $this->assertDatabaseHas('shopping_cart_items', [
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
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

        $this->assertEquals($expectedFillable, $item->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
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

        $this->assertIsArray($item->options);
        $this->assertEquals($options, $item->options);
        $this->assertIsFloat($item->price);
        $this->assertEquals(10.50, $item->price);
        $this->assertIsBool($item->is_selected);
        $this->assertTrue($item->is_selected);
    }

    /** @test */
    public function it_belongs_to_cart()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $this->assertInstanceOf(ShoppingCart::class, $item->cart);
        $this->assertEquals($this->cart->id, $item->cart->id);
        $this->assertEquals('user123', $item->cart->customer_id);
    }

    /** @test */
    public function it_calculates_subtotal()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 3,
            'price' => 15.50,
            'name' => 'Test Product',
        ]);

        $this->assertEquals(46.50, $item->getSubtotal());
    }

    /** @test */
    public function it_calculates_subtotal_with_zero_quantity()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 0,
            'price' => 15.50,
            'name' => 'Test Product',
        ]);

        $this->assertEquals(0.00, $item->getSubtotal());
    }

    /** @test */
    public function it_calculates_subtotal_with_zero_price()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 3,
            'price' => 0.00,
            'name' => 'Free Product',
        ]);

        $this->assertEquals(0.00, $item->getSubtotal());
    }

    /** @test */
    public function it_checks_if_item_has_options()
    {
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

        $this->assertTrue($itemWithOptions->hasOptions());
        $this->assertFalse($itemWithoutOptions->hasOptions());
        $this->assertFalse($itemWithEmptyOptions->hasOptions());
    }

    /** @test */
    public function it_gets_option_value()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => ['color' => 'red', 'size' => 'large'],
        ]);

        $this->assertEquals('red', $item->getOption('color'));
        $this->assertEquals('large', $item->getOption('size'));
        $this->assertNull($item->getOption('material'));
        $this->assertEquals('default', $item->getOption('material', 'default'));
    }

    /** @test */
    public function it_gets_option_value_from_item_without_options()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => null,
        ]);

        $this->assertNull($item->getOption('color'));
        $this->assertEquals('default', $item->getOption('color', 'default'));
    }

    /** @test */
    public function it_checks_if_item_matches_product_and_options()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => ['color' => 'red', 'size' => 'large'],
        ]);

        $this->assertTrue($item->matches(1, ['color' => 'red', 'size' => 'large']));
        $this->assertTrue($item->matches(1, ['size' => 'large', 'color' => 'red'])); // Order doesn't matter
        $this->assertFalse($item->matches(1, ['color' => 'blue', 'size' => 'large']));
        $this->assertFalse($item->matches(2, ['color' => 'red', 'size' => 'large']));
        $this->assertFalse($item->matches(1, ['color' => 'red']));
        $this->assertFalse($item->matches(1, ['color' => 'red', 'size' => 'large', 'material' => 'cotton']));
    }

    /** @test */
    public function it_matches_item_without_options()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => null,
        ]);

        $this->assertTrue($item->matches(1, null));
        $this->assertTrue($item->matches(1, []));
        $this->assertFalse($item->matches(1, ['color' => 'red']));
        $this->assertFalse($item->matches(2, null));
    }

    /** @test */
    public function it_updates_quantity()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $item->updateQuantity(5);

        $this->assertEquals(5, $item->quantity);
        $this->assertDatabaseHas('shopping_cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function it_increments_quantity()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $item->incrementQuantity(3);

        $this->assertEquals(5, $item->quantity);
        $this->assertDatabaseHas('shopping_cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function it_decrements_quantity()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 5,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $item->decrementQuantity(2);

        $this->assertEquals(3, $item->quantity);
        $this->assertDatabaseHas('shopping_cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    /** @test */
    public function it_prevents_negative_quantity_when_decrementing()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ]);

        $item->decrementQuantity(5);

        $this->assertEquals(0, $item->quantity);
        $this->assertDatabaseHas('shopping_cart_items', [
            'id' => $item->id,
            'quantity' => 0,
        ]);
    }

    /** @test */
    public function it_toggles_selection_status()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'is_selected' => true,
        ]);

        $item->toggleSelection();
        $this->assertFalse($item->is_selected);

        $item->toggleSelection();
        $this->assertTrue($item->is_selected);
    }

    /** @test */
    public function it_sets_selection_status()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'is_selected' => false,
        ]);

        $item->setSelected(true);
        $this->assertTrue($item->is_selected);

        $item->setSelected(false);
        $this->assertFalse($item->is_selected);
    }

    /** @test */
    public function it_converts_to_array()
    {
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

        $this->assertIsArray($itemArray);
        $this->assertEquals($this->cart->id, $itemArray['cart_id']);
        $this->assertEquals(1, $itemArray['product_id']);
        $this->assertEquals(2, $itemArray['quantity']);
        $this->assertEquals(10.50, $itemArray['price']);
        $this->assertEquals('Test Product', $itemArray['name']);
        $this->assertEquals('A test product', $itemArray['description']);
        $this->assertEquals(['color' => 'red'], $itemArray['options']);
        $this->assertTrue($itemArray['is_selected']);
    }

    /** @test */
    public function it_handles_null_options_gracefully()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => null,
        ]);

        $this->assertNull($item->options);
        $this->assertFalse($item->hasOptions());
        $this->assertNull($item->getOption('color'));
        $this->assertTrue($item->matches(1, null));
        $this->assertTrue($item->matches(1, []));
    }

    /** @test */
    public function it_handles_empty_options_array()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'name' => 'Test Product',
            'options' => [],
        ]);

        $this->assertIsArray($item->options);
        $this->assertEmpty($item->options);
        $this->assertFalse($item->hasOptions());
        $this->assertNull($item->getOption('color'));
        $this->assertTrue($item->matches(1, []));
        $this->assertTrue($item->matches(1, null));
    }

    /** @test */
    public function it_calculates_subtotal_with_decimal_precision()
    {
        $item = ShoppingCartItem::create([
            'cart_id' => $this->cart->id,
            'product_id' => 1,
            'quantity' => 3,
            'price' => 9.99,
            'name' => 'Test Product',
        ]);

        $this->assertEquals(29.97, $item->getSubtotal());
    }
}