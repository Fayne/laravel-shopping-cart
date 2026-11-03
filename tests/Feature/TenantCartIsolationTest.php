<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class TenantCartIsolationTest extends IntegrationTestCase
{
    public function test_same_customer_id_gets_a_separate_active_cart_in_each_tenant(): void
    {
        $context = new class implements CartContextResolver
        {
            public string $tenant = 'first';

            public function tenantId(): string
            {
                return $this->tenant;
            }

            public function customerId(): int
            {
                return 1;
            }

            public function sessionId(): ?string
            {
                return null;
            }
        };
        $this->app->instance(CartContextResolver::class, $context);
        $cart = $this->app->make(ShoppingCart::class);

        $first = $cart->addProduct(Product::create(['price' => 10]), 2);
        $item = $first->items->sole();
        $checkout = Checkout::create(['out_trade_no' => 'tenant-first']);
        $storage = $this->app->make(CartStorageInterface::class);
        $recoveryKey = $cart->getCacheKeyForDeactivatedCartId();
        $optimizer = new \Fayne\ShoppingCart\Cache\CartCacheOptimizer('array');
        $optimizer->rememberCart('1', fn () => 'first-cart');
        $context->tenant = 'second';
        $this->assertSame('second-cart', $optimizer->rememberCart('1', fn () => 'second-cart'));

        $this->assertNull($cart->getCart());
        $this->assertNotSame($recoveryKey, $cart->getCacheKeyForDeactivatedCartId());
        $this->assertSame(0, $storage->getItemsQty(1));
        $this->assertNull($storage->getCartItem($item->getKey()));
        $this->assertFalse($storage->removeCartItem($item->getKey()));
        $this->assertNull(Checkout::find($checkout->getKey()));
        $cart->activateCart($first->getKey());
        $cart->emptyCart();
        $this->assertNull($cart->getCart());

        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => $cart->consumeOrderItems($first->id, 'foreign-order', [[
                'cart_item_id' => $item->id,
                'sku_id' => $item->itemable_id,
                'quantity' => 2,
                'selected' => true,
            ]]));
            $this->fail('Expected the foreign cart to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('shopping_cart_order_consumptions', 0);
        $second = $cart->create();
        $this->assertNotSame($first->getKey(), $second->getKey());

        $context->tenant = 'first';
        $this->assertSame($first->getKey(), $cart->getCart()?->getKey());
        $this->assertSame(2, $storage->getItemsQty(1));
        $this->assertFalse($storage->updateCartItem($item->id, ['cart_id' => $second->id]));
        $this->assertFalse($storage->updateItems($first->id, [['id' => $item->id, 'cart_id' => $second->id]]));
        $second->is_active = false;
        $this->assertFalse($storage->updateCart($second));
        $this->assertDatabaseHas('shopping_cart_items', ['id' => $item->id, 'cart_id' => $first->id]);
        $this->assertSame('first-cart', $optimizer->rememberCart('1', fn () => 'unexpected'));

        $this->app->forgetInstance(CartContextResolver::class);
        $this->assertNull($cart->getCart());
        $this->assertNull($storage->getCartItem($item->id));
        $this->assertNull(Checkout::find($checkout->id));
        $this->assertFalse($storage->updateCart($first));
    }

    public function test_missing_resolver_blocks_reads_and_writes(): void
    {
        config(['shopping-cart.tenancy.enabled' => true]);
        $cart = $this->app->make(ShoppingCart::class);

        foreach (['getCart', 'create'] as $method) {
            try {
                $cart->{$method}();
                $this->fail("Expected {$method} to reject missing context.");
            } catch (HttpException $exception) {
                $this->assertSame(503, $exception->getStatusCode());
            }
        }

        $this->assertDatabaseCount('shopping_carts', 0);
    }
}
