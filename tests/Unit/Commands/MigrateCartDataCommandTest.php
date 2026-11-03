<?php

namespace Fayne\ShoppingCart\Tests\Unit\Commands;

use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;
use Fayne\ShoppingCart\Models\ShoppingCartItem;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;

class MigrateCartDataCommandTest extends IntegrationTestCase
{
    public function test_driver_switch_does_not_copy_authoritative_rows(): void
    {
        config(['shopping-cart.storage.drivers.cache.store' => 'array']);
        app(ShoppingCart::class)->addProduct(Product::create(['price' => 10]), 2);
        $this->artisan('cart:migrate-data', ['--from' => 'database', '--to' => 'cache', '--force' => true])->assertExitCode(0);
        $this->assertSame(1, CartModel::count());
        $this->assertSame(1, ShoppingCartItem::count());
        $this->assertSame(2, ShoppingCartItem::first()->quantity);
    }
    public function test_dry_run_preserves_data(): void
    {
        app(ShoppingCart::class)->addProduct(Product::create(['price' => 10]), 1);
        $this->artisan('cart:migrate-data', ['--from' => 'database', '--to' => 'cache', '--dry-run' => true])->assertExitCode(0);
        $this->assertSame(1, CartModel::count());
    }
    public function test_invalid_batch_size_is_rejected(): void
    {
        $this->artisan('cart:migrate-data', ['--from' => 'database', '--to' => 'cache', '--batch-size' => 0, '--force' => true])->assertExitCode(1);
    }
}
