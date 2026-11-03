<?php

namespace Fayne\ShoppingCart\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Fayne\ShoppingCart\ShoppingCartServiceProvider;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected string $tablePrefix = '';

    protected function getPackageProviders($app) { return [SanctumServiceProvider::class, ShoppingCartServiceProvider::class]; }
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.user_model', User::class);
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => $this->tablePrefix, 'foreign_key_constraints' => true]);
        $app->instance(\Fayne\ShoppingCart\Contracts\CartSkuQuery::class, new class implements \Fayne\ShoppingCart\Contracts\CartSkuQuery
        {
            public function forTenant(string $model, int|string $tenantId): \Illuminate\Database\Eloquent\Builder
            {
                return $model::query();
            }
        });
        $app['config']->set('shopping-cart.routes.enabled', true);
        $app['config']->set('shopping-cart.models.skus.model', Fixtures\Product::class);
        $app['config']->set('shopping-cart.checkout.validator', fn ($sku, $quantity) => true);
    }
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) { $t->id();
            $t->string('name'); });
        Schema::create('products', function (Blueprint $t) { $t->ulid('id')->primary();
            $t->decimal('price', 12, 2);
            $t->timestamps(); });
        Schema::create('product_skus', function (Blueprint $t) { $t->ulid('id')->primary(); });
        foreach (glob(__DIR__ . '/../database/migrations/*.php') as $file) { (require $file)->up(); }
        DB::table('users')->insert(['id' => 1, 'name' => 'One']);
        $user = new User;
        $user->id = 1;
        $this->actingAs($user);
    }
}
