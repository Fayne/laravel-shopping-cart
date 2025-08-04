<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Support\Facades\File;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Cache\CartCacheOptimizer as CacheOptimizer;
use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Console\Commands\MigrateCartDataCommand;
use MobileNowGroup\ShoppingCart\Console\Commands\CartPerformanceMonitorCommand;
use MobileNowGroup\ShoppingCart\Listeners\CartOperationListener;

class ShoppingCartServiceProvider extends PackageServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred()
    {
        return true;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ShoppingCart::class,
            CartStorageManager::class,
            \MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer::class,
            'shopping-cart',
            'cart.storage',
            CartStorageInterface::class,
        ];
    }
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('shopping-cart')
            ->hasConfigFile();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->bound('config')) {
            $config = $this->app->make('config');
            $configPath = __DIR__ . '/../config/shopping-cart.php';
            $key = 'shopping-cart';
            
            if (! ($this->app instanceof \Illuminate\Contracts\Foundation\CachesConfiguration && $this->app->configurationIsCached())) {
                $existingConfig = $config->get($key, []);
                if (!is_array($existingConfig)) {
                    $existingConfig = [];
                }
                $config->set($key, array_merge(
                    require $configPath, $existingConfig
                ));
            }
        }

        // 注册存储管理器
        $this->app->singleton('cart.storage', function ($app) {
            return new CartStorageManager($app);
        });
        
        $this->app->singleton(CartStorageManager::class, function ($app) {
            return $app['cart.storage'];
        });

        // 注册存储接口
        $this->app->bind(CartStorageInterface::class, function ($app) {
            return $app['cart.storage']->driver();
        });

        // 注册购物车服务
        $this->app->singleton('shopping-cart', function ($app) {
            return new ShoppingCart($app[CartStorageInterface::class]);
        });
        
        $this->app->singleton(ShoppingCart::class, function ($app) {
            return $app['shopping-cart'];
        });

        // 注册缓存优化器
        $this->app->singleton(CartCacheOptimizer::class);
        $this->app->singleton(CacheOptimizer::class, function ($app) {
            return $app[CartCacheOptimizer::class];
        });
    }

    /**
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/shopping-cart.php' => config_path('shopping-cart.php')
        ], 'config');

        // 发布迁移文件
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations')
        ], 'migrations');

        if ($this->shouldLoadMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        if (config('shopping-cart.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes.php');
        }

        // 注册事件监听器
        if (config('shopping-cart.events.enabled', true)) {
            $this->registerEventListeners();
        }

        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCartDataCommand::class,
                CartPerformanceMonitorCommand::class,
            ]);
        }
    }

    /**
     * 注册事件监听器
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        $this->app['events']->listen(
            \MobileNowGroup\ShoppingCart\Events\CartItemAdded::class,
            \MobileNowGroup\ShoppingCart\Listeners\CartOperationListener::class . '@handleProductAdded'
        );
    }

    /**
     * @return bool
     */
    protected function shouldLoadMigrations(): bool
    {
        $publishedMigration = $this->findPublishedMigration();

        return $publishedMigration === null;
    }

    /**
     * @return string|null
     */
    protected function findPublishedMigration(): ?string
    {
        $migrationDir = database_path('migrations/');

        if (! File::isDirectory($migrationDir)) {
            return null;
        }

        $migrationFiles = File::glob($migrationDir . '*_create_cart_table.php');

        return ! empty($migrationFiles) ? basename($migrationFiles[0]) : null;
    }
}
