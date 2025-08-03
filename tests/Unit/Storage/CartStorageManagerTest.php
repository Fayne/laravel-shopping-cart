<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Storage\CacheCartStorage;
use MobileNowGroup\ShoppingCart\Storage\RedisCartStorage;
use MobileNowGroup\ShoppingCart\Storage\CartStorageInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = new CartStorageManager($this->app);
});

test('it can create database driver', function () {
    Config::set('shopping-cart.storage.default', 'database');
    Config::set('shopping-cart.storage.drivers.database', []);

    $driver = $this->manager->driver('database');

    expect($driver)->toBeInstanceOf(DatabaseCartStorage::class);
});

test('it can create cache driver', function () {
    Config::set('shopping-cart.storage.default', 'cache');
    Config::set('shopping-cart.storage.drivers.cache', [
        'store' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);

    $driver = $this->manager->driver('cache');

    expect($driver)->toBeInstanceOf(CacheCartStorage::class);
});

test('it can create redis driver', function () {
    Config::set('shopping-cart.storage.default', 'redis');
    Config::set('shopping-cart.storage.drivers.redis', [
        'connection' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);

    $driver = $this->manager->driver('redis');

    expect($driver)->toBeInstanceOf(RedisCartStorage::class);
});

test('it can create layered driver', function () {
    Config::set('shopping-cart.storage.default', 'layered');
    Config::set('shopping-cart.storage.drivers.layered', [
        'enable_cache' => true,
        'enable_redis' => true,
    ]);
    Config::set('shopping-cart.storage.drivers.cache', [
        'store' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);
    Config::set('shopping-cart.storage.drivers.redis', [
        'connection' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);

    $driver = $this->manager->driver('layered');

    expect($driver)->toBeInstanceOf(RedisCartStorage::class);
});

test('it uses default driver when no driver specified', function () {
    Config::set('shopping-cart.storage.default', 'database');
    Config::set('shopping-cart.storage.drivers.database', []);

    $driver = $this->manager->driver();

    expect($driver)->toBeInstanceOf(DatabaseCartStorage::class);
});

test('it returns same instance for same driver', function () {
    Config::set('shopping-cart.storage.default', 'database');
    Config::set('shopping-cart.storage.drivers.database', []);

    $driver1 = $this->manager->driver('database');
    $driver2 = $this->manager->driver('database');

    expect($driver1)->toBe($driver2);
});

test('it throws exception for invalid driver', function () {
    $this->manager->driver('invalid');
})->throws(\InvalidArgumentException::class, 'Driver [invalid] not supported.');

test('it can extend with custom driver', function () {
    $customDriver = new DatabaseCartStorage();
    
    $this->manager->extend('custom', function () use ($customDriver) {
        return $customDriver;
    });

    $driver = $this->manager->driver('custom');

    expect($driver)->toBe($customDriver);
});

test('it gets default driver name from config', function () {
    Config::set('shopping-cart.storage.default', 'redis');

    $defaultDriver = $this->manager->getDefaultDriver();

    expect($defaultDriver)->toEqual('redis');
});

test('it falls back to database when no default configured', function () {
    Config::set('shopping-cart.storage.default', null);

    $defaultDriver = $this->manager->getDefaultDriver();

    expect($defaultDriver)->toEqual('database');
});

test('it creates layered driver with cache only', function () {
    Config::set('shopping-cart.storage.drivers.layered', [
        'enable_cache' => true,
        'enable_redis' => false,
    ]);
    Config::set('shopping-cart.storage.drivers.cache', [
        'store' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);

    $driver = $this->manager->driver('layered');

    expect($driver)->toBeInstanceOf(CacheCartStorage::class);
});

test('it creates layered driver with redis only', function () {
    Config::set('shopping-cart.storage.drivers.layered', [
        'enable_cache' => false,
        'enable_redis' => true,
    ]);
    Config::set('shopping-cart.storage.drivers.redis', [
        'connection' => 'default',
        'prefix' => 'cart',
        'ttl' => 3600,
    ]);

    $driver = $this->manager->driver('layered');

    expect($driver)->toBeInstanceOf(RedisCartStorage::class);
});

test('it creates layered driver with database only when no cache or redis', function () {
    Config::set('shopping-cart.storage.drivers.layered', [
        'enable_cache' => false,
        'enable_redis' => false,
    ]);

    $driver = $this->manager->driver('layered');

    expect($driver)->toBeInstanceOf(DatabaseCartStorage::class);
});

test('it uses correct cache configuration', function () {
    Config::set('shopping-cart.storage.drivers.cache', [
        'store' => 'redis',
        'prefix' => 'custom_cart',
        'ttl' => 7200,
    ]);

    $driver = $this->manager->driver('cache');

    expect($driver)->toBeInstanceOf(CacheCartStorage::class);
    // Note: We can't easily test the internal configuration without exposing it,
    // but we can verify the driver is created successfully
});

test('it uses correct redis configuration', function () {
    Config::set('shopping-cart.storage.drivers.redis', [
        'connection' => 'cache',
        'prefix' => 'custom_cart',
        'ttl' => 7200,
    ]);

    $driver = $this->manager->driver('redis');

    expect($driver)->toBeInstanceOf(RedisCartStorage::class);
    // Note: We can't easily test the internal configuration without exposing it,
    // but we can verify the driver is created successfully
});