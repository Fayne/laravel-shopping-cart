<?php

namespace Fayne\ShoppingCart\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Fayne\ShoppingCart\ShoppingCartServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class TestCase extends Orchestra
{
    // use RefreshDatabase; // 暂时禁用以避免事务冲突

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = false;

    /**
     * 跟踪已创建的alias mock
     */
    protected static $createdAliases = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 清理之前的mock状态
        \Mockery::close();
        static::$createdAliases = [];

        // 彻底重置数据库连接
        $this->resetDatabaseConnections();

        // 直接运行迁移文件
        $this->runMigrations();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Fayne\\ShoppingCart\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        // 彻底重置数据库连接
        $this->resetDatabaseConnections();

        parent::tearDown();
    }

    /**
     * 安全地创建alias mock，避免重复创建
     */
    protected function createSafeAliasMock($className)
    {
        if (!isset(static::$createdAliases[$className])) {
            static::$createdAliases[$className] = \Mockery::mock('alias:' . $className);
        }

        return static::$createdAliases[$className];
    }

    /**
     * 重置数据库连接
     */
    protected function resetDatabaseConnections()
    {
        if (app()->bound('db')) {
            $db = app('db');

            // 关闭所有连接
            foreach ($db->getConnections() as $connection) {
                $connection->disconnect();
            }

            // 清除连接池
            $db->purge();
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            ShoppingCartServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // 确保购物车配置正确加载
        $app['config']->set('shopping-cart.models.carts.table', 'shopping_carts');
        $app['config']->set('shopping-cart.models.cart_items.table', 'shopping_cart_items');
        $app['config']->set('shopping-cart.models.users.table', 'users');
    }

    /**
     * 创建模拟用户
     */
    protected function createMockUser($id = 1)
    {
        return new class($id) implements Authenticatable {
            public $id;
            public $name;
            public $email;

            public function __construct($id)
            {
                $this->id = (string) $id;
                $this->name = 'Test User ' . $id;
                $this->email = 'test' . $id . '@example.com';
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPassword()
            {
                return 'password';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value)
            {
                // Do nothing
            }

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        };
    }

    /**
     * 运行迁移文件
     */
    protected function runMigrations()
    {
        config(['database.default' => 'testing', 'app.user_model' => User::class]);
        foreach (['checkout_items', 'checkout', 'shopping_cart_items', 'shopping_carts', 'products', 'product_skus'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->string('sku');
            $table->timestamps();
        });
        Schema::create('product_skus', fn (Blueprint $table) => $table->ulid('id')->primary());
        foreach (glob(__DIR__ . '/../database/migrations/*.php') as $file) {
            (require $file)->up();
        }
    }

}

/**
 * 测试用的简单 Product 类
 */
class Product extends Model
{
    use HasUlids;
    protected $fillable = ['name', 'price', 'sku'];

    public function getPrice()
    {
        return $this->price ?? 10.00;
    }

    public function getName()
    {
        return $this->name ?? 'Test Product';
    }

    public function getSku()
    {
        return $this->sku ?? 'TEST-SKU';
    }

    public function getWeight()
    {
        return 1.0;
    }
}

function createMockUserForShoppingCart($customerId) {
    $user = \Mockery::mock(User::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($customerId);
    $user->shouldReceive('getAttribute')->with('name')->andReturn('Test User');
    $user->shouldReceive('setAttribute')->with('name', 'Test User')->andReturnSelf();
    $user->shouldReceive('offsetExists')->andReturn(true);
    $user->shouldReceive('offsetGet')->with('name')->andReturn('Test User');
    $user->name = 'Test User';

    return $user;
}
