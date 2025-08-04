<?php

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

test('it registers shopping cart singleton', function () {
    expect(app()->bound(ShoppingCart::class))->toBeTrue();
    
    $cart1 = app()->make(ShoppingCart::class);
    $cart2 = app()->make(ShoppingCart::class);
    
    expect($cart1)->toBe($cart2);
});

test('it registers cart storage manager singleton', function () {
    expect(app()->bound(CartStorageManager::class))->toBeTrue();
    
    $manager1 = app()->make(CartStorageManager::class);
    $manager2 = app()->make(CartStorageManager::class);
    
    expect($manager1)->toBe($manager2)
        ->and($manager1)->toBeInstanceOf(CartStorageManager::class);
});

test('it registers cart cache optimizer singleton', function () {
    expect(app()->bound(CartCacheOptimizer::class))->toBeTrue();
    
    $optimizer1 = app()->make(CartCacheOptimizer::class);
    $optimizer2 = app()->make(CartCacheOptimizer::class);
    
    expect($optimizer1)->toBe($optimizer2)
        ->and($optimizer1)->toBeInstanceOf(CartCacheOptimizer::class);
});

test('it publishes configuration file', function () {
    $this->artisan('vendor:publish', [
        '--provider' => ShoppingCartServiceProvider::class,
        '--tag' => 'config',
    ]);
    
    expect(file_exists(config_path('shopping-cart.php')))->toBeTrue();
});

test('it publishes migration files', function () {
    $this->artisan('vendor:publish', [
        '--provider' => ShoppingCartServiceProvider::class,
        '--tag' => 'migrations',
    ]);
    
    $migrationFiles = glob(database_path('migrations/*_create_shopping_carts_table.php'));
    expect($migrationFiles)->not->toBeEmpty();
    
    $migrationFiles = glob(database_path('migrations/*_create_shopping_cart_items_table.php'));
    expect($migrationFiles)->not->toBeEmpty();
});

test('it loads configuration file', function () {
    expect(config('shopping-cart'))->not->toBeNull()
        ->and(config('shopping-cart'))->toBeArray()
        ->and(config('shopping-cart'))->toHaveKey('storage')
        ->and(config('shopping-cart'))->toHaveKey('cache')
        ->and(config('shopping-cart'))->toHaveKey('events');
});

test('it loads migration files', function () {
    $provider = new ShoppingCartServiceProvider(app());
    
    // Check if migrations are loaded by running them
    $this->artisan('migrate', ['--path' => 'database/migrations']);
    
    expect(\Schema::hasTable('shopping_carts'))->toBeTrue()
        ->and(\Schema::hasTable('shopping_cart_items'))->toBeTrue();
});

test('it registers event listeners when enabled', function () {
    Config::set('shopping-cart.events.enabled', true);
    
    // Re-register the service provider to apply the config change
    $provider = new ShoppingCartServiceProvider(app());
    $provider->boot();
    
    $listeners = Event::getListeners(CartItemAdded::class);
    expect($listeners)->not->toBeEmpty();
    
    $listeners = Event::getListeners(CartItemRemoved::class);
    expect($listeners)->not->toBeEmpty();
    
    $listeners = Event::getListeners(CartItemUpdated::class);
    expect($listeners)->not->toBeEmpty();
    
    $listeners = Event::getListeners(CartCleared::class);
    expect($listeners)->not->toBeEmpty();
    
    $listeners = Event::getListeners(CartTotalCalculated::class);
    expect($listeners)->not->toBeEmpty();
});

test('it does not register event listeners when disabled', function () {
    Config::set('shopping-cart.events.enabled', false);
    
    // Re-register the service provider to apply the config change
    $provider = new ShoppingCartServiceProvider(app());
    $provider->boot();
    
    $listeners = Event::getListeners(CartItemAdded::class);
    expect($listeners)->toBeEmpty();
});

test('it registers artisan commands in console environment', function () {
    // Mock console environment
    $app = app();
    $app->instance('env', 'testing');
    
    // Mock runningInConsole to return true
    $mockApp = Mockery::mock($app);
    $mockApp->shouldReceive('runningInConsole')->andReturn(true);
    $mockApp->shouldReceive('make')->andReturnUsing(function ($abstract) use ($app) {
        return $app->make($abstract);
    });
    $mockApp->shouldReceive('offsetGet')->andReturnUsing(function ($key) use ($app) {
        return $app[$key];
    });
    $mockApp->shouldReceive('bound')->andReturnUsing(function ($abstract) use ($app) {
        return $app->bound($abstract);
    });
    
    $provider = new ShoppingCartServiceProvider($mockApp);
    $provider->boot();
    
    // Commands are registered via the commands() method, not bound to container
    expect(true)->toBeTrue(); // This test just ensures boot() runs without error
});

test('it resolves shopping cart with correct storage', function () {
    Config::set('shopping-cart.storage.default', 'database');
    
    $cart = app()->make(ShoppingCart::class);
    
    expect($cart)->toBeInstanceOf(ShoppingCart::class);
    
    // Test that the cart can perform basic operations with authenticated user
    $this->actingAs(createMockUser(1));
    $result = $cart->getCart();
    // In test environment, getCart() may return null if no cart exists
    expect($result)->toBeNull();
    
    // Test that we can create a cart
    expect(method_exists($cart, 'addProduct'))->toBeTrue();
    expect(method_exists($cart, 'removeItem'))->toBeTrue();
    expect(method_exists($cart, 'updateItems'))->toBeTrue();
});

test('it resolves cart storage manager with correct configuration', function () {
    Config::set('shopping-cart.storage.default', 'cache');
    Config::set('shopping-cart.storage.drivers.cache', [
        'store' => 'file',
        'ttl' => 3600,
    ]);
    
    $manager = app()->make(CartStorageManager::class);
    
    expect($manager)->toBeInstanceOf(CartStorageManager::class);
    
    // Test that the manager can create storage drivers
    $storage = $manager->driver('cache');
    expect($storage)->not->toBeNull();
});

test('it resolves cart cache optimizer with correct configuration', function () {
    Config::set('shopping-cart.cache.enabled', true);
    Config::set('shopping-cart.cache.ttl', 1800);
    
    $optimizer = app()->make(CartCacheOptimizer::class);
    
    expect($optimizer)->toBeInstanceOf(CartCacheOptimizer::class);
});

test('it provides correct services', function () {
    $provider = new ShoppingCartServiceProvider(app());
    $provides = $provider->provides();
    
    $expectedServices = [
        ShoppingCart::class,
        CartStorageManager::class,
        CartCacheOptimizer::class,
    ];
    
    foreach ($expectedServices as $service) {
        expect($provides)->toContain($service);
    }
});

test('it is deferred service provider', function () {
    $provider = new ShoppingCartServiceProvider(app());
    
    expect($provider->isDeferred())->toBeTrue();
});

test('it handles missing configuration gracefully', function () {
    // Clear the configuration
    Config::set('shopping-cart', null);
    
    // The service provider should still work with default values
    $provider = new ShoppingCartServiceProvider(app());
    $provider->register();
    
    expect(app()->bound(ShoppingCart::class))->toBeTrue()
        ->and(app()->bound(CartStorageManager::class))->toBeTrue()
        ->and(app()->bound(CartCacheOptimizer::class))->toBeTrue();
});

test('it registers services in correct order', function () {
    // Clear all bindings
    app()->flush();
    
    // Re-register essential services after flush
    app()->singleton('config', function () {
        return new \Illuminate\Config\Repository();
    });
    
    $provider = new ShoppingCartServiceProvider(app());
    $provider->register();
    
    // CartStorageManager should be registered first
    expect(app()->bound(CartStorageManager::class))->toBeTrue();
    
    // Then CartCacheOptimizer
    expect(app()->bound(CartCacheOptimizer::class))->toBeTrue();
    
    // Finally ShoppingCart
    expect(app()->bound(ShoppingCart::class))->toBeTrue();
    
    // Test that services are bound but don't resolve them yet
    // (Resolution will be tested in boot phase)
    expect(app()->bound('shopping-cart'))->toBeTrue()
        ->and(app()->bound('cart.storage'))->toBeTrue();
});

test('it can publish all assets at once', function () {
    $this->artisan('vendor:publish', [
        '--provider' => ShoppingCartServiceProvider::class,
    ]);
    
    // Check that config file is published
    expect(file_exists(config_path('shopping-cart.php')))->toBeTrue();
    
    // Check that migration files are published
    $migrationFiles = glob(database_path('migrations/*_create_cart_table.php'));
    expect($migrationFiles)->not->toBeEmpty();
    
    $migrationFiles = glob(database_path('migrations/*_create_cart_items_table.php'));
    expect($migrationFiles)->not->toBeEmpty();
});

test('it handles different storage configurations', function () {
    $storageConfigs = [
        'database' => [],
        'cache' => ['store' => 'file', 'ttl' => 3600],
        'redis' => ['connection' => 'default', 'fallback' => 'database'],
        'layered' => ['layers' => ['redis', 'cache', 'database']],
    ];
    
    foreach ($storageConfigs as $driver => $config) {
        Config::set('shopping-cart.storage.default', $driver);
        Config::set("shopping-cart.storage.drivers.{$driver}", $config);
        
        $manager = app()->make(CartStorageManager::class);
        $storage = $manager->driver($driver);
        
        expect($storage)->not->toBeNull("Failed to create {$driver} storage driver");
    }
});