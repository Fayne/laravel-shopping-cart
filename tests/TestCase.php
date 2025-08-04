<?php

namespace MobileNowGroup\ShoppingCart\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider;

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
            fn (string $modelName) => 'MobileNowGroup\\ShoppingCart\\database\\factories\\' . class_basename($modelName) . 'Factory'
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
        return new class($id) implements \Illuminate\Contracts\Auth\Authenticatable {
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
         // 确保使用正确的数据库连接
         config(['database.default' => 'testing']);
         
         // 手动创建表结构，因为包开发环境没有 artisan 命令
         // 强制删除现有表
         try {
             Schema::connection('testing')->dropIfExists('shopping_cart_items');
             Schema::connection('testing')->dropIfExists('shopping_carts');
             Schema::connection('testing')->dropIfExists('products');
         } catch (\Exception $e) {
             // 忽略删除错误
         }
         
         // 确保表确实被删除了
         if (Schema::connection('testing')->hasTable('shopping_carts') || Schema::connection('testing')->hasTable('shopping_cart_items') || Schema::connection('testing')->hasTable('products')) {
             throw new \Exception('Failed to drop existing tables');
         }
         
         // 创建 shopping_carts 表
         Schema::connection('testing')->create('shopping_carts', function (Blueprint $table) {
             $table->increments('id');
             $table->text('customer_name')->nullable();
             $table->string('shipping_method')->nullable();
             $table->string('coupon_code')->nullable();
             $table->boolean('is_gift')->default(0);
             $table->boolean('is_temp')->default(false);
             $table->integer('items_count')->nullable();
             $table->decimal('items_qty', 12)->nullable();
             $table->decimal('grand_total', 12)->default(0)->nullable();
             $table->decimal('base_grand_total', 12)->default(0)->nullable();
             $table->decimal('sub_total', 12)->default(0)->nullable();
             $table->decimal('base_sub_total', 12)->default(0)->nullable();
             $table->decimal('discount', 12)->default(0)->nullable();
             $table->decimal('base_discount', 12)->default(0)->nullable();
             $table->string('checkout_method')->nullable();
             $table->boolean('is_active')->nullable()->default(1);
             $table->dateTime('conversion_time')->nullable();
             $table->ulid('customer_id')->nullable();
             $table->string('session_id')->nullable();
             $table->json('metadata')->nullable();
             $table->timestamps();
         });
         
         // 创建 shopping_cart_items 表
         Schema::connection('testing')->create('shopping_cart_items', function (Blueprint $table) {
             $table->id();
             $table->integer('quantity')->unsigned()->default(0);
             $table->string('sku')->nullable();
             $table->string('type')->nullable();
             $table->string('name')->nullable();
             $table->string('coupon_code')->nullable();
             $table->decimal('weight', 12)->default(1);
             $table->decimal('total_weight', 12)->default(0);
             $table->decimal('base_total_weight', 12)->default(0);
             $table->decimal('price', 12)->default(1);
             $table->decimal('base_price', 12)->default(0);
             $table->decimal('custom_price', 12)->default(0);
             $table->decimal('total', 12)->default(0);
             $table->decimal('base_total', 12)->default(0);
             $table->decimal('discount_percent', 12)->default(0);
             $table->decimal('discount_amount', 12)->default(0);
             $table->decimal('base_discount_amount', 12)->default(0);
             $table->json('additional')->nullable();
             $table->morphs('itemable');
             $table->boolean('selected')->default(false);
             $table->integer('cart_id')->unsigned();
             $table->foreign('cart_id')->references('id')->on('shopping_carts')->onDelete('cascade');
             $table->timestamps();
         });
         
         // 创建 products 表用于测试
         Schema::connection('testing')->create('products', function (Blueprint $table) {
             $table->id();
             $table->string('name');
             $table->decimal('price', 8, 2);
             $table->string('sku');
             $table->timestamps();
         });
         
         // 验证表是否创建成功
         if (!Schema::connection('testing')->hasTable('shopping_carts') || !Schema::connection('testing')->hasTable('shopping_cart_items')) {
             throw new \Exception('Failed to create required tables');
         }
     }

}

/**
 * 测试用的简单 Product 类
 */
class Product extends \Illuminate\Database\Eloquent\Model
{
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
    $user = \Mockery::mock(\Illuminate\Foundation\Auth\User::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($customerId);
    $user->shouldReceive('getAttribute')->with('name')->andReturn('Test User');
    $user->shouldReceive('setAttribute')->with('name', 'Test User')->andReturnSelf();
    $user->shouldReceive('offsetExists')->andReturn(true);
    $user->shouldReceive('offsetGet')->with('name')->andReturn('Test User');
    $user->name = 'Test User';
    return $user;
}
