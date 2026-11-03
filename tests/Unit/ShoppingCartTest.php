<?php

namespace Fayne\ShoppingCart\Tests\Unit;

use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class ShoppingCartTest extends IntegrationTestCase
{
    public function test_add_and_merge_quantities(): void
    {
        $cart = app(ShoppingCart::class);
        $product = Product::create(['price' => 1.23]);
        $cart->addProduct($product, 2);
        $result = $cart->addProduct($product, 3);
        $this->assertCount(1, $result->items);
        $this->assertSame(5, $result->items->first()->quantity);
        $this->assertEquals(6.15, $result->grand_total);
        $this->assertSame(1, $cart->itemsCount());
        $this->assertSame(5, $cart->itemsQty());
    }
    public function test_quantity_selection_and_empty_cart(): void
    {
        $cart = app(ShoppingCart::class);
        $result = $cart->addProduct(Product::create(['price' => 2.50]), 2);
        $id = $result->items->first()->id;
        $this->assertEquals(10, $cart->updateItems($id, 4)->grand_total);
        $this->assertEquals(0, $cart->setCartItemSelection($id, false)->grand_total);
        $this->assertEquals(10, $cart->setAllCartItemsSelected(true)->grand_total);
        $this->assertCount(0, $cart->emptyCart()->items);
        $this->assertSame(0, $cart->itemsQty());
    }
    public function test_remove_only_target_item(): void
    {
        $cart = app(ShoppingCart::class);
        $first = Product::create(['price' => 1]);
        $second = Product::create(['price' => 2]);
        $cart->addProduct($first, 1);
        $result = $cart->addProduct($second, 2);
        $cart->removeItem($result->items->firstWhere('itemable_id', $first->id)->id);
        $this->assertCount(1, $cart->getCart()->items);
        $this->assertEquals(4, $cart->getCart()->grand_total);
    }
    public function test_create_reuses_active_cart(): void
    {
        $cart = app(ShoppingCart::class);
        $first = $cart->create();
        $this->assertSame($first->id, $cart->create()->id);
    }
}
