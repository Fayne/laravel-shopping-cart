<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Models\CheckoutItem;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Illuminate\Database\QueryException;

class PrefixedMigrationTest extends IntegrationTestCase
{
    protected string $tablePrefix = 'shop_';

    public function test_fresh_prefixed_schema_preserves_checkout_history(): void
    {
        $product = Product::create(['price' => 10]);
        DB::table('product_skus')->insert(['id' => $product->id]);
        $checkout = Checkout::create(['user_id' => 1, 'out_trade_no' => 'history', 'expires_at' => now()->addHour()]);
        $item = CheckoutItem::create(['checkout_id' => $checkout->id, 'product_id' => $product->id, 'sku_id' => $product->id, 'name_snapshot' => 'Original', 'price_snapshot' => 10, 'quantity' => 2]);
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertEquals(0, $item->fresh()->discount_amount);
        $this->expectException(QueryException::class);
        $product->delete();
    }
}
