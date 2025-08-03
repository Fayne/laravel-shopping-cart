<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Support\Facades\File;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Cache\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Console\Commands;
use MobileNowGroup\ShoppingCart\Listeners\CartOperationListener;

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
        $this->mergeConfigFrom(__DIR__ . '/../config/shopping-cart.php', 'shopping-cart');

        $this->publishes([__DIR__ . '/../config/shopping-cart.php' => config_path('shopping-cart.php')], 'config');

        //        $this->app['events']->listen(Logout::class, function () {
        //            if ($this->app['config']->get('shopping-cart.destroy_on_logout')) {
        //                $this->app->make(SessionManager::class)->forget('cart');
        //            }
        //        });

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        // 注册存储管理器
        $this->app->singleton('cart.storage', function ($app) {
            return new CartStorageManager($app);
        });

        // 注册存储接口
        $this->app->bind(CartStorageInterface::class, function ($app) {
            return $app['cart.storage']->driver();
        });

        // 注册购物车服务
        $this->app->singleton('shopping-cart', function ($app) {
            return new ShoppingCart($app[CartStorageInterface::class]);
        });

        // 注册缓存优化器
        $this->app->singleton(CartCacheOptimizer::class);

        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MigrateCartDataCommand::class,
                Commands\CartPerformanceMonitorCommand::class,
            ]);
        }
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->shouldLoadMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/Migrations');
        }

        if (config('shopping-cart.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes.php');
        }
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
