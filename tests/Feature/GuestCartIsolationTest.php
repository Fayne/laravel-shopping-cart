<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class GuestCartIsolationTest extends IntegrationTestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('shopping-cart.models.users.enforce_cart_foreign_key', false);
        $app['config']->set('shopping-cart.routes.guests', true);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

    }

    public function test_guest_cart_is_isolated_by_session_and_tenant_and_is_not_merged_at_login(): void
    {
        $context = new class implements CartContextResolver
        {
            public string $tenant = 'first';

            public string $session = 'session-one';

            public ?int $customer = null;

            public function tenantId(): string
            {
                return $this->tenant;
            }

            public function customerId(): ?int
            {
                return $this->customer;
            }

            public function sessionId(): ?string
            {
                return $this->customer === null ? $this->session : null;
            }
        };
        $this->app->instance(CartContextResolver::class, $context);
        $cart = $this->app->make(ShoppingCart::class);
        $product = Product::create(['price' => 10]);
        $guest = $cart->addProduct($product, 1);

        $itemId = $guest->items->sole()->id;
        $context->session = 'session-two';
        $this->assertNull($cart->getCart());
        $this->deleteJson('/modules/shopping-cart/api/cart/remove-item', [
            'item_id' => $itemId, 'session_id' => 'session-one', 'tenant_id' => 'first', 'customer_id' => 1,
        ])->assertNotFound();
        $context->session = 'session-one';
        $context->tenant = 'second';
        $this->assertNull($cart->getCart());
        $context->tenant = 'first';
        $context->customer = 1;
        $this->assertNull($cart->getCart());
        $context->customer = null;
        $this->assertSame($guest->id, $cart->getCart()?->id);

        request()->merge(['is_buy_now' => true]);
        $cart->deactivateCurrentCartIfBuyNowIsActive();
        $temporary = $cart->addProduct($product, 2, true);
        $this->assertTrue($temporary->is_temp);
        $this->assertNotSame($guest->id, $temporary->id);

        $context->session = 'session-two';
        $cart->activateCartIfCacheHasDeactivatedCartId();
        $this->assertNull($cart->getCart());
        $context->session = 'session-one';

        $cart->transaction(function () use ($cart): void {
            $cart->deActivateCart();
            $cart->activateCartIfCacheHasDeactivatedCartId();
        });
        $this->assertSame($guest->id, $cart->getCart()?->id);
        $this->assertSame(1, $cart->getCart()?->items->sole()->quantity);
        $this->deleteJson('/modules/shopping-cart/api/cart/remove-item', ['item_id' => $itemId])->assertSuccessful();
        $this->assertCount(0, $cart->getCart()->items);

        $admin = new \Illuminate\Foundation\Auth\User;
        $admin->id = 2;
        $admin->name = 'Administrator';
        $this->actingAs($admin);
        $this->assertDatabaseHas('shopping_carts', ['id' => $temporary->id]);
        $this->assertDatabaseHas('shopping_cart_items', ['cart_id' => $temporary->id, 'quantity' => 2]);
        $context->customer = 3;
        $customerCart = $cart->addProduct($product, 1);
        $this->assertSame('3', (string) $customerCart->customer_id);
        $this->assertNull($customerCart->customer_name);
        $this->deleteJson('/modules/shopping-cart/api/cart/remove-item', [
            'item_id' => $customerCart->items->sole()->id,
        ])->assertSuccessful();
        $this->assertCount(0, $cart->getCart()->items);
    }
}
