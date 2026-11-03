<?php

namespace Fayne\ShoppingCart\Tests\Infrastructure;

use Illuminate\Support\Facades\DB;
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
            'host' => '127.0.0.1', 'port' => (int) getenv('CART_ACCEPTANCE_REDIS_PORT'), 'database' => 0,
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
            foreach (['checkout_items', 'checkout', 'shopping_cart_items', 'shopping_carts', 'products', 'product_skus', 'users'] as $table) {
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
