<?php

namespace Fayne\ShoppingCart\Tests\Unit;

use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Fayne\ShoppingCart\Exceptions\CartClientException;

class ShoppingCartPestTest extends IntegrationTestCase
{
    public function test_invalid_quantity_preserves_existing_cart(): void
    {
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 5]);
        $cart = $service->addProduct($product, 2);
        try { $service->updateItems($cart->items->first()->id, 0);
            $this->fail('Expected validation failure'); } catch (CartClientException $e) { $this->assertSame(2, $service->getCart()->items->first()->quantity); }
    }
    public function test_empty_cart_can_be_reused(): void
    {
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 5]);
        $first = $service->addProduct($product, 1);
        $service->emptyCart();
        $this->assertSame($first->id, $service->addProduct($product, 3)->id);
        $this->assertSame(3, $service->itemsQty());
    }
}
