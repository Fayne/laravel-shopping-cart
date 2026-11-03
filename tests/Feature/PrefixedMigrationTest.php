<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Models\CheckoutItem;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Illuminate\Database\QueryException;

class PrefixedMigrationTest extends IntegrationTestCase
{
    protected string $tablePrefix = 'shop_';

    public function test_upgrade_preserves_existing_rows_host_columns_indexes_and_triggers(): void
    {
        $product = Product::create(['price' => 10]);
        DB::table('product_skus')->insert(['id' => $product->id]);
        $checkout = Checkout::create(['user_id' => 1, 'out_trade_no' => 'history', 'expires_at' => now()->addHour()]);
        $item = CheckoutItem::create(['checkout_id' => $checkout->id, 'product_id' => $product->id, 'sku_id' => $product->id, 'name_snapshot' => 'Original', 'price_snapshot' => 10, 'quantity' => 2]);
        $migration = require __DIR__ . '/../../database/migrations/2026_11_02_000001_preserve_checkout_history.php';
        $migration->down();
        Schema::table('checkout_items', function (Blueprint $table) {
            $table->integer('double_quantity')->virtualAs('quantity * 2');
            $table->index('quantity', 'host_quantity_index');
        });
        DB::statement('CREATE TRIGGER host_quantity_trigger AFTER UPDATE OF quantity ON shop_checkout_items BEGIN UPDATE shop_checkout_items SET name_snapshot = \'Updated\' WHERE id = NEW.id; END');
        $migration->up();
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(4, $item->fresh()->double_quantity);
        $item->update(['quantity' => 3]);
        $this->assertSame('Updated', $item->fresh()->name_snapshot);
        $this->assertSame(6, $item->fresh()->double_quantity);
        $this->assertNotNull(DB::selectOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'host_quantity_index'"));
        $this->assertTrue((bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->expectException(QueryException::class);
        $product->delete();
    }
}
