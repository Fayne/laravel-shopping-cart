<?php

namespace Fayne\ShoppingCart;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Storage\CartStorageManager;
use Fayne\ShoppingCart\Cache\CartCacheOptimizer as CacheOptimizer;
use Fayne\ShoppingCart\Optimization\CartCacheOptimizer;
use Fayne\ShoppingCart\Console\Commands\MigrateCartDataCommand;
use Fayne\ShoppingCart\Console\Commands\CartPerformanceMonitorCommand;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Fayne\ShoppingCart\Events\CartCleared;
use Fayne\ShoppingCart\Events\CartItemAdded;
use Fayne\ShoppingCart\Events\CartItemRemoved;
use Fayne\ShoppingCart\Events\CartItemUpdated;
use Fayne\ShoppingCart\Events\CartTotalCalculated;
use Fayne\ShoppingCart\Listeners\CartOperationListener;
use Fayne\ShoppingCart\Listeners\CollectTotalsListener;

class ShoppingCartServiceProvider extends PackageServiceProvider
{
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

            if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
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
        $this->app->scoped('cart.storage', function ($app) {
            return new CartStorageManager($app);
        });

        $this->app->scoped(CartStorageManager::class, function ($app) {
            return $app['cart.storage'];
        });

        // 注册存储接口
        $this->app->bind(CartStorageInterface::class, function ($app) {
            return $app['cart.storage']->driver();
        });

        // 注册购物车服务
        $this->app->scoped('shopping-cart', function ($app) {
            return new ShoppingCart($app[CartStorageInterface::class]);
        });

        $this->app->scoped(ShoppingCart::class, function ($app) {
            return $app['shopping-cart'];
        });

        // 注册缓存优化器
        $this->app->singleton(CartCacheOptimizer::class, fn () => new CartCacheOptimizer);
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
            __DIR__ . '/../config/shopping-cart.php' => config_path('shopping-cart.php'),
        ], 'config');

        // 发布迁移文件
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        foreach (glob(__DIR__ . '/../database/migrations/*.php') as $migration) {
            $name = explode('_', basename($migration), 5)[4];
            if (!File::glob(database_path('migrations/*_' . $name))) {
                $this->loadMigrationsFrom($migration);
            }
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

        // Register routes
        $this->app->booted(function () {
            $this->routes();
        });
    }

    /**
     * 注册事件监听器
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        $this->app['events']->listen(
            CartItemAdded::class,
            CartOperationListener::class . '@handleProductAdded'
        );

        $this->app['events']->listen(
            CartItemRemoved::class,
            CartOperationListener::class . '@handleProductRemoved'
        );

        $this->app['events']->listen(
            CartItemUpdated::class,
            CartOperationListener::class . '@handleProductUpdated'
        );

        $this->app['events']->listen(
            CartCleared::class,
            CartOperationListener::class . '@handleCartCleared'
        );

        $this->app['events']->listen(
            CartTotalCalculated::class,
            CollectTotalsListener::class . '@handle'
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

    /**
     * @return void
     */
    private function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (config('shopping-cart.routes.enabled', true)) {
            Route::name('modules.shopping-cart.')
                ->prefix('modules/shopping-cart')
                ->group(__DIR__ . '/routes.php');
        }
    }
}
