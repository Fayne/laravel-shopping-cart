<?php

namespace MobileNowGroup\ShoppingCart\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
         // 清理现有表
         \Illuminate\Support\Facades\Schema::dropIfExists('shopping_cart_items');
         \Illuminate\Support\Facades\Schema::dropIfExists('shopping_carts');
        // 创建购物车表
        \Illuminate\Support\Facades\Schema::create('shopping_carts', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('customer_id')->index();
            $table->string('customer_name')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        
        // 创建购物车项目表
         \Illuminate\Support\Facades\Schema::create('shopping_cart_items', function (\Illuminate\Database\Schema\Blueprint $table) {
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
    }

}
