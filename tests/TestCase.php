<?php

namespace MobileNowGroup\ShoppingCart\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider;

class TestCase extends Orchestra
{
    // use RefreshDatabase; // 暂时注释掉，手动管理数据库
    
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'MobileNowGroup\\ShoppingCart\\database\\factories\\' . class_basename($modelName) . 'Factory'
        );
        
        // 手动清理和重建数据库
        $this->refreshTestDatabase();
    }
    
    protected function refreshTestDatabase()
    {
        // 确保表不存在
        \Illuminate\Support\Facades\Schema::dropIfExists('shopping_cart_items');
        \Illuminate\Support\Facades\Schema::dropIfExists('shopping_carts');
        \Illuminate\Support\Facades\Schema::dropIfExists('users');
        
        // 强制刷新数据库连接
        \Illuminate\Support\Facades\DB::purge('testing');
        \Illuminate\Support\Facades\DB::reconnect('testing');
        
        // 只有在表不存在时才创建用户表
        if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
            \Illuminate\Support\Facades\Schema::create('users', function ($table) {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamps();
            });
        }
        
        // 只有在表不存在时才创建购物车表
        if (!\Illuminate\Support\Facades\Schema::hasTable('shopping_carts')) {
            \Illuminate\Support\Facades\Schema::create('shopping_carts', function ($table) {
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
                $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
                $table->string('session_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
        
        // 只有在表不存在时才创建购物车项目表
        if (!\Illuminate\Support\Facades\Schema::hasTable('shopping_cart_items')) {
            \Illuminate\Support\Facades\Schema::create('shopping_cart_items', function ($table) {
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

}
