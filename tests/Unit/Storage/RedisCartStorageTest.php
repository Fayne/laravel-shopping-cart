<?php

namespace Fayne\ShoppingCart\Tests\Unit\Storage;

use Fayne\ShoppingCart\Storage\DatabaseCartStorage;
use Fayne\ShoppingCart\Storage\RedisCartStorage;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class RedisCartStorageTest extends IntegrationTestCase
{
    public function test_aliases_and_bulk_selection_do_not_cross_cart_boundaries(): void
    {
        $storage = new RedisCartStorage(new DatabaseCartStorage, 'custom-prefix', 60, 'missing');
        $cart = $storage->createCart(['customer_id' => 1]);
        $product = Product::create(['price' => 5]);
        $item = $storage->addItem($cart->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 2]);
        $this->assertFalse($storage->toggleItemsSelection('other', [$item->id], true));
        $this->assertFalse($storage->removeItem('other', $item->id));
        $this->assertTrue($storage->toggleItemsSelection($cart->id, [$item->id], true));
        $this->assertTrue($storage->getCartItem($item->id)->selected);
        $this->assertCount(1, $storage->getCartItems($cart->id));
        $this->assertTrue($storage->removeItem($cart->id, $item->id));
        $this->assertCount(0, $storage->getCartItems($cart->id));
    }
}
