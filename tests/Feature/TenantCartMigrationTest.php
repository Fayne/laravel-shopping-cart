<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Fayne\ShoppingCart\Models\ShoppingCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class TenantCartMigrationTest extends IntegrationTestCase
{
    public function test_explicit_backfill_preserves_unassigned_cart_and_checkout_history(): void
    {
        $cart = ShoppingCart::create(['customer_id' => 1, 'customer_name' => 'One']);

        $checkoutId = (string) Str::ulid();
        DB::table('checkout')->insert([
            'id' => $checkoutId,
            'out_trade_no' => 'legacy-checkout',
            'user_id' => 1,
            'total_amount' => 18.50,
        ]);

        $sku = (string) Str::ulid();
        DB::table('products')->insert(['id' => $sku, 'price' => 18.50]);
        DB::table('product_skus')->insert(['id' => $sku]);
        $line = (string) Str::ulid();
        DB::table('checkout_items')->insert([
            'id' => $line, 'checkout_id' => $checkoutId, 'product_id' => $sku, 'sku_id' => $sku,
            'name_snapshot' => 'Historical SKU', 'price_snapshot' => 18.50, 'quantity' => 1,
        ]);

        $this->assertDatabaseHas('shopping_carts', [
            'id' => $cart->getKey(),
            'customer_id' => 1,
            'tenant_id' => null,
        ]);
        $this->assertDatabaseHas('checkout', [
            'id' => $checkoutId,
            'out_trade_no' => 'legacy-checkout',
            'total_amount' => 18.50,
            'tenant_id' => null,
        ]);
        $backfill = new \Fayne\ShoppingCart\Storage\TenantOwnershipBackfill;
        try {
            $backfill->apply([$cart->getKey() => 'store'], ['unknown' => 'store']);
            $this->fail('Expected the unknown Checkout mapping to be rejected.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('shopping_carts', ['id' => $cart->getKey(), 'tenant_id' => null]);
        }

        $backfill->apply([$cart->getKey() => 'store'], [$checkoutId => 'store']);
        $this->assertDatabaseHas('shopping_carts', ['id' => $cart->getKey(), 'tenant_id' => 'store']);
        $this->assertDatabaseHas('checkout', ['id' => $checkoutId, 'tenant_id' => 'store']);
        $this->assertDatabaseHas('checkout_items', ['id' => $line, 'price_snapshot' => 18.50]);
    }
    public function test_database_rejects_a_second_active_cart_for_the_same_tenant_guest(): void
    {
        $cart = ['tenant_id' => 'store', 'session_id' => 'guest', 'is_active' => true];
        DB::table('shopping_carts')->insert($cart + ['id' => (string) Str::ulid()]);
        DB::table('shopping_carts')->insert(array_replace($cart, [
            'id' => (string) Str::ulid(), 'is_active' => false,
        ]));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('shopping_carts')->insert($cart + ['id' => (string) Str::ulid(), 'is_temp' => true]);
    }
}
