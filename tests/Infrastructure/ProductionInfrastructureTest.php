<?php

namespace Fayne\ShoppingCart\Tests\Infrastructure;

use Illuminate\Support\Facades\DB;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Fayne\ShoppingCart\Http\Controllers\CheckoutController;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Models\ShoppingCart as Cart;
use Fayne\ShoppingCart\Models\ShoppingCartItem;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Storage\DatabaseCartStorage;
use Fayne\ShoppingCart\Storage\RedisCartStorage;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Illuminate\Support\Str;

/** Only run against the disposable cart_acceptance database created for these tests. */
class ProductionInfrastructureTest extends IntegrationTestCase
{
    private bool $schemaReady = false;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('CART_ACCEPTANCE_MYSQL_PORT'),
            'database' => 'cart_acceptance', 'username' => 'cart', 'password' => 'cart_test_only',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]);
        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.acceptance', [
            'host' => '127.0.0.1', 'port' => (int) getenv('CART_ACCEPTANCE_REDIS_PORT'), 'database' => (int) (getenv('CART_ACCEPTANCE_REDIS_DB') ?: 0),
        ]);
    }

    protected function setUp(): void
    {
        if (!getenv('CART_ACCEPTANCE_MYSQL_PORT') || !getenv('CART_ACCEPTANCE_REDIS_PORT')) {
            $this->markTestSkipped('Disposable MySQL/Redis acceptance ports are not configured.');
        }
        parent::setUp();
        $this->schemaReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->schemaReady) {
            Schema::disableForeignKeyConstraints();
            foreach (['shopping_cart_order_consumptions', 'shopping_cart_guest_locks', 'checkout_items', 'checkout', 'shopping_cart_items', 'shopping_carts', 'shopping_cart_skus', 'products', 'product_skus', 'users'] as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();
        }
        parent::tearDown();
    }

    public function test_parallel_adds_create_one_cart_without_lost_quantities(): void
    {
        $product = Product::create(['price' => 1.23]);
        $results = $this->parallel(8, function () use ($product) {
            app(ShoppingCart::class)->addProduct($product, 1);

            return 200;
        });
        $this->assertSame(array_fill(0, 8, 200), $results);
        $this->assertSame(1, Cart::count());
        $this->assertSame(1, ShoppingCartItem::count());
        $this->assertSame(8, ShoppingCartItem::first()->quantity);
        $this->assertEquals(9.84, Cart::first()->grand_total);
    }

    public function test_parallel_adds_isolate_tenants_with_the_same_customer(): void
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
        app()->instance(CartContextResolver::class, $context);

        $product = Product::create(['price' => 1.23]);
        foreach (['first', 'second'] as $tenant) {
            $context->tenant = $tenant;
            $this->assertNull(app(ShoppingCart::class)->getCart());
            $results = $this->parallel(4, function () use ($product) {
                app(ShoppingCart::class)->addProduct($product, 1);

                return 200;
            });
            $this->assertSame(array_fill(0, 4, 200), $results);
            $this->assertSame(1, Cart::count());
            $this->assertSame(4, ShoppingCartItem::firstOrFail()->quantity);
        }

        $this->assertSame(2, DB::table('shopping_carts')->count());
    }

    public function test_parallel_checkout_consumes_selected_items_once(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('product_id')->nullable());
        $product = Product::create(['price' => 12.34]);
        $product->update(['product_id' => $product->id]);
        DB::table('product_skus')->insert(['id' => $product->id]);
        config(['shopping-cart.models.skus.model' => Product::class, 'shopping-cart.order_number_generator_class' => AcceptanceOrderNumber::class]);
        app(ShoppingCart::class)->addProduct($product, 2);
        $results = $this->parallel(2, fn () => app(CheckoutController::class)(request())->getStatusCode());
        sort($results);
        $this->assertSame([200, 400], $results);
        $this->assertSame(1, Checkout::count());
        $this->assertSame(0, ShoppingCartItem::count());
        $this->assertEquals(24.68, Checkout::first()->total_amount);
    }

    public function test_checkout_failure_rolls_back_stock_and_cart_mutations(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_id')->nullable();
            $table->integer('stock')->default(5);
        });
        foreach ([10, 20] as $price) {
            $product = Product::create(['price' => $price]);
            $product->update(['product_id' => $product->id]);
            DB::table('product_skus')->insert(['id' => $product->id]);
            app(ShoppingCart::class)->addProduct($product, 1);
        }
        config(['shopping-cart.models.skus.model' => Product::class, 'shopping-cart.order_number_generator_class' => AcceptanceOrderNumber::class]);
        $calls = 0;
        config(['shopping-cart.checkout.validator' => function ($sku, $quantity) use (&$calls) {
            DB::table('products')->where('id', $sku->id)->decrement('stock', $quantity);
            if (++$calls === 2) { throw new \RuntimeException('Inventory rejected'); }

            return true;
        }]);
        try { app(CheckoutController::class)(request());
            $this->fail('Expected rejection'); } catch (\RuntimeException $error) { $this->assertSame('Inventory rejected', $error->getMessage()); }
        $this->assertSame([5, 5], Product::orderBy('id')->pluck('stock')->all());
        $this->assertSame(2, ShoppingCartItem::count());
        $this->assertSame(0, Checkout::count());
    }

    public function test_real_redis_connection_and_driver_preserve_state(): void
    {
        $redis = Redis::connection('acceptance');
        $redis->set('cart-acceptance-probe', 'connected');
        $this->assertSame('connected', $redis->get('cart-acceptance-probe'));
        $storage = new RedisCartStorage(new DatabaseCartStorage, 'acceptance', 60, 'acceptance');
        $service = new ShoppingCart($storage);
        $cart = $service->addProduct(Product::create(['price' => 2.50]), 2);
        $this->assertCount(1, $service->getCart()->items);
        $service->updateItems($cart->items->first()->id, 3);
        $this->assertEquals(7.50, $service->getCart()->grand_total);
        $service->emptyCart();
        $this->assertCount(0, $service->getCart()->items);
        $redis->del('cart-acceptance-probe');
    }

    public function test_parallel_consumption_of_the_same_order_only_removes_quoted_lines(): void
    {
        $service = app(ShoppingCart::class);
        $sku = Product::create(['price' => 2]);
        $cart = $service->addProduct($sku, 2);
        $line = $cart->items->sole();
        $cart = $service->addProduct(Product::create(['price' => 3]), 1);
        $other = $cart->items->firstWhere('id', '!=', $line->id);
        $service->setCartItemSelection($other->id, false);
        $quote = [[
            'cart_item_id' => $line->id,
            'sku_id' => $sku->id,
            'quantity' => 2,
            'selected' => true,
        ]];

        $results = $this->parallel(2, function () use ($service, $cart, $quote) {
            return DB::transaction(function () use ($service, $cart, $quote) {
                // Establish a read snapshot before waiting for the cart lock.
                DB::table('shopping_cart_order_consumptions')->count();
                $service->consumeOrderItems($cart->id, 'parallel-order', $quote);

                return 200;
            });
        });

        $this->assertSame([200, 200], $results);
        $this->assertSame($other->id, $service->getCart()->items->sole()->id);
        $this->assertDatabaseCount('shopping_cart_order_consumptions', 1);
    }

    public function test_redis_driver_keeps_tenant_and_session_contexts_isolated_in_one_process(): void
    {
        $context = new class implements CartContextResolver
        {
            public string $tenant = 'first';
            public ?int $customer = 1;
            public string $session = 'one';

            public function tenantId(): string { return $this->tenant; }
            public function customerId(): ?int { return $this->customer; }
            public function sessionId(): ?string { return $this->customer === null ? $this->session : null; }
        };
        app()->instance(CartContextResolver::class, $context);
        $service = new ShoppingCart(new RedisCartStorage(new DatabaseCartStorage, 'acceptance', 60, 'acceptance'));
        $sku = Product::create(['price' => 2]);
        $customerCart = $service->addProduct($sku, 1);
        $customerKey = $service->getCacheKeyForDeactivatedCartId();

        $context->tenant = 'second';
        $this->assertNull($service->getCart());
        $this->assertNotSame($customerKey, $service->getCacheKeyForDeactivatedCartId());
        $context->tenant = 'first';
        $context->customer = null;
        $guestCart = $service->addProduct($sku, 2);
        $guestKey = $service->getCacheKeyForDeactivatedCartId();

        $context->session = 'two';
        $this->assertNull($service->getCart());
        $this->assertNotSame($guestKey, $service->getCacheKeyForDeactivatedCartId());
        $context->session = 'one';
        $this->assertSame($guestCart->id, $service->getCart()?->id);
        $context->customer = 1;
        $this->assertSame($customerCart->id, $service->getCart()?->id);
    }

    private function parallel(int $count, callable $operation): array
    {
        $directory = sys_get_temp_dir() . '/cart-acceptance-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        DB::disconnect();
        $children = [];
        for ($index = 0; $index < $count; $index++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Cannot start concurrent worker');
            }
            if ($pid === 0) {
                while (!is_file($directory . '/start')) { usleep(1000); }
                try {
                    DB::reconnect();
                    $result = $operation();
                } catch (\Throwable $error) {
                    $result = $error->getMessage();
                }
                file_put_contents($directory . '/' . $index, json_encode($result));
                exit(0);
            }
            $children[] = $pid;
        }
        touch($directory . '/start');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();
        $results = [];
        for ($index = 0; $index < $count; $index++) {
            $results[] = json_decode(file_get_contents($directory . '/' . $index), true);
            unlink($directory . '/' . $index);
        }
        unlink($directory . '/start');
        rmdir($directory);

        return $results;
    }
}

class AcceptanceOrderNumber
{
    public function __invoke(): string { return (string) Str::ulid(); }
}
