<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Support\Facades\File;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            __DIR__ . '/Database/Migrations' => database_path('migrations'),
        ], 'migrations');

        $this->app->bind('shopping-cart', ShoppingCart::class);
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->shouldLoadMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
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

        $migrationFiles = File::glob($migrationDir . '*_create_shopping_cart_table.php');

        return ! empty($migrationFiles) ? basename($migrationFiles[0]) : null;
    }
}
