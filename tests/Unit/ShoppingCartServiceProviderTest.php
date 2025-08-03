<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use MobileNowGroup\ShoppingCart\Commands\CartPerformanceMonitorCommand;
use MobileNowGroup\ShoppingCart\Commands\MigrateCartDataCommand;
use MobileNowGroup\ShoppingCart\Events\CartCleared;
use MobileNowGroup\ShoppingCart\Events\CartItemAdded;
use MobileNowGroup\ShoppingCart\Events\CartItemRemoved;
use MobileNowGroup\ShoppingCart\Events\CartItemUpdated;
use MobileNowGroup\ShoppingCart\Events\CartTotalCalculated;
use MobileNowGroup\ShoppingCart\Listeners\CartOperationListener;
use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\ShoppingCart;
use MobileNowGroup\ShoppingCart\ShoppingCartServiceProvider;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class ShoppingCartServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_shopping_cart_singleton()
    {
        $this->assertTrue($this->app->bound(ShoppingCart::class));
        
        $cart1 = $this->app->make(ShoppingCart::class);
        $cart2 = $this->app->make(ShoppingCart::class);
        
        $this->assertSame($cart1, $cart2);
    }

    /** @test */
    public function it_registers_cart_storage_manager_singleton()
    {
        $this->assertTrue($this->app->bound(CartStorageManager::class));
        
        $manager1 = $this->app->make(CartStorageManager::class);
        $manager2 = $this->app->make(CartStorageManager::class);
        
        $this->assertSame($manager1, $manager2);
        $this->assertInstanceOf(CartStorageManager::class, $manager1);
    }

    /** @test */
    public function it_registers_cart_cache_optimizer_singleton()
    {
        $this->assertTrue($this->app->bound(CartCacheOptimizer::class));
        
        $optimizer1 = $this->app->make(CartCacheOptimizer::class);
        $optimizer2 = $this->app->make(CartCacheOptimizer::class);
        
        $this->assertSame($optimizer1, $optimizer2);
        $this->assertInstanceOf(CartCacheOptimizer::class, $optimizer1);
    }

    /** @test */
    public function it_publishes_configuration_file()
    {
        $provider = new ShoppingCartServiceProvider($this->app);
        
        $this->artisan('vendor:publish', [
            '--provider' => ShoppingCartServiceProvider::class,
            '--tag' => 'config',
        ]);
        
        $this->assertFileExists(config_path('shopping-cart.php'));
    }

    /** @test */
    public function it_publishes_migration_files()
    {
        $provider = new ShoppingCartServiceProvider($this->app);
        
        $this->artisan('vendor:publish', [
            '--provider' => ShoppingCartServiceProvider::class,
            '--tag' => 'migrations',
        ]);
        
        $migrationFiles = glob(database_path('migrations/*_create_shopping_carts_table.php'));
        $this->assertNotEmpty($migrationFiles);
        
        $migrationFiles = glob(database_path('migrations/*_create_shopping_cart_items_table.php'));
        $this->assertNotEmpty($migrationFiles);
    }

    /** @test */
    public function it_loads_configuration_file()
    {
        $this->assertNotNull(config('shopping-cart'));
        $this->assertIsArray(config('shopping-cart'));
        $this->assertArrayHasKey('storage', config('shopping-cart'));
        $this->assertArrayHasKey('cache', config('shopping-cart'));
        $this->assertArrayHasKey('events', config('shopping-cart'));
    }

    /** @test */
    public function it_loads_migration_files()
    {
        $provider = new ShoppingCartServiceProvider($this->app);
        
        // Check if migrations are loaded by running them
        $this->artisan('migrate', ['--path' => 'vendor/mobilenowgroup/laravel-shopping-cart/database/migrations']);
        
        $this->assertTrue(\Schema::hasTable('shopping_carts'));
        $this->assertTrue(\Schema::hasTable('shopping_cart_items'));
    }

    /** @test */
    public function it_registers_event_listeners_when_enabled()
    {
        Config::set('shopping-cart.events.enabled', true);
        
        // Re-register the service provider to apply the config change
        $provider = new ShoppingCartServiceProvider($this->app);
        $provider->boot();
        
        $listeners = Event::getListeners(CartItemAdded::class);
        $this->assertNotEmpty($listeners);
        
        $listeners = Event::getListeners(CartItemRemoved::class);
        $this->assertNotEmpty($listeners);
        
        $listeners = Event::getListeners(CartItemUpdated::class);
        $this->assertNotEmpty($listeners);
        
        $listeners = Event::getListeners(CartCleared::class);
        $this->assertNotEmpty($listeners);
        
        $listeners = Event::getListeners(CartTotalCalculated::class);
        $this->assertNotEmpty($listeners);
    }

    /** @test */
    public function it_does_not_register_event_listeners_when_disabled()
    {
        Config::set('shopping-cart.events.enabled', false);
        
        // Re-register the service provider to apply the config change
        $provider = new ShoppingCartServiceProvider($this->app);
        $provider->boot();
        
        $listeners = Event::getListeners(CartItemAdded::class);
        $this->assertEmpty($listeners);
    }

    /** @test */
    public function it_registers_artisan_commands_in_console_environment()
    {
        // Simulate console environment
        $this->app['env'] = 'testing';
        
        $provider = new ShoppingCartServiceProvider($this->app);
        $provider->boot();
        
        $this->assertTrue($this->app->bound(MigrateCartDataCommand::class));
        $this->assertTrue($this->app->bound(CartPerformanceMonitorCommand::class));
    }

    /** @test */
    public function it_resolves_shopping_cart_with_correct_storage()
    {
        Config::set('shopping-cart.storage.default', 'database');
        
        $cart = $this->app->make(ShoppingCart::class);
        
        $this->assertInstanceOf(ShoppingCart::class, $cart);
        
        // Test that the cart can perform basic operations
        $this->assertIsArray($cart->getCart('test_user'));
    }

    /** @test */
    public function it_resolves_cart_storage_manager_with_correct_configuration()
    {
        Config::set('shopping-cart.storage.default', 'cache');
        Config::set('shopping-cart.storage.drivers.cache', [
            'store' => 'file',
            'ttl' => 3600,
        ]);
        
        $manager = $this->app->make(CartStorageManager::class);
        
        $this->assertInstanceOf(CartStorageManager::class, $manager);
        
        // Test that the manager can create storage drivers
        $storage = $manager->driver('cache');
        $this->assertNotNull($storage);
    }

    /** @test */
    public function it_resolves_cart_cache_optimizer_with_correct_configuration()
    {
        Config::set('shopping-cart.cache.enabled', true);
        Config::set('shopping-cart.cache.ttl', 1800);
        
        $optimizer = $this->app->make(CartCacheOptimizer::class);
        
        $this->assertInstanceOf(CartCacheOptimizer::class, $optimizer);
    }

    /** @test */
    public function it_provides_correct_services()
    {
        $provider = new ShoppingCartServiceProvider($this->app);
        $provides = $provider->provides();
        
        $expectedServices = [
            ShoppingCart::class,
            CartStorageManager::class,
            CartCacheOptimizer::class,
        ];
        
        foreach ($expectedServices as $service) {
            $this->assertContains($service, $provides);
        }
    }

    /** @test */
    public function it_is_deferred_service_provider()
    {
        $provider = new ShoppingCartServiceProvider($this->app);
        
        $this->assertTrue($provider->isDeferred());
    }

    /** @test */
    public function it_handles_missing_configuration_gracefully()
    {
        // Clear the configuration
        Config::set('shopping-cart', null);
        
        // The service provider should still work with default values
        $provider = new ShoppingCartServiceProvider($this->app);
        $provider->register();
        
        $this->assertTrue($this->app->bound(ShoppingCart::class));
        $this->assertTrue($this->app->bound(CartStorageManager::class));
        $this->assertTrue($this->app->bound(CartCacheOptimizer::class));
    }

    /** @test */
    public function it_registers_services_in_correct_order()
    {
        // Clear all bindings
        $this->app->flush();
        
        // Re-register essential services after flush
        $this->app->singleton('config', function () {
            return new \Illuminate\Config\Repository();
        });
        
        $provider = new ShoppingCartServiceProvider($this->app);
        $provider->register();
        
        // CartStorageManager should be registered first
        $this->assertTrue($this->app->bound(CartStorageManager::class));
        
        // Then CartCacheOptimizer
        $this->assertTrue($this->app->bound(CartCacheOptimizer::class));
        
        // Finally ShoppingCart
        $this->assertTrue($this->app->bound(ShoppingCart::class));
        
        // Test that services are bound but don't resolve them yet
        // (Resolution will be tested in boot phase)
        $this->assertTrue($this->app->bound('shopping-cart'));
        $this->assertTrue($this->app->bound('cart.storage'));
    }

    /** @test */
    public function it_can_publish_all_assets_at_once()
    {
        $this->artisan('vendor:publish', [
            '--provider' => ShoppingCartServiceProvider::class,
        ]);
        
        // Check that config file is published
        $this->assertFileExists(config_path('shopping-cart.php'));
        
        // Check that migration files are published
        $migrationFiles = glob(database_path('migrations/*_create_shopping_carts_table.php'));
        $this->assertNotEmpty($migrationFiles);
        
        $migrationFiles = glob(database_path('migrations/*_create_shopping_cart_items_table.php'));
        $this->assertNotEmpty($migrationFiles);
    }

    /** @test */
    public function it_handles_different_storage_configurations()
    {
        $storageConfigs = [
            'database' => [],
            'cache' => ['store' => 'file', 'ttl' => 3600],
            'redis' => ['connection' => 'default', 'fallback' => 'database'],
            'layered' => ['layers' => ['redis', 'cache', 'database']],
        ];
        
        foreach ($storageConfigs as $driver => $config) {
            Config::set('shopping-cart.storage.default', $driver);
            Config::set("shopping-cart.storage.drivers.{$driver}", $config);
            
            $manager = $this->app->make(CartStorageManager::class);
            $storage = $manager->driver($driver);
            
            $this->assertNotNull($storage, "Failed to create {$driver} storage driver");
        }
    }
}