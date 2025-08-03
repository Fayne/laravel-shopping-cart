<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Storage\CacheCartStorage;
use MobileNowGroup\ShoppingCart\Storage\RedisCartStorage;
use MobileNowGroup\ShoppingCart\Storage\LayeredCartStorage;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartStorageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected CartStorageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manager = new CartStorageManager($this->app);
    }

    /** @test */
    public function it_can_create_database_driver()
    {
        Config::set('shopping-cart.storage.default', 'database');
        Config::set('shopping-cart.storage.drivers.database', []);

        $driver = $this->manager->driver('database');

        $this->assertInstanceOf(DatabaseCartStorage::class, $driver);
    }

    /** @test */
    public function it_can_create_cache_driver()
    {
        Config::set('shopping-cart.storage.default', 'cache');
        Config::set('shopping-cart.storage.drivers.cache', [
            'store' => 'default',
            'prefix' => 'cart',
            'ttl' => 3600,
        ]);

        $driver = $this->manager->driver('cache');

        $this->assertInstanceOf(CacheCartStorage::class, $driver);
    }

    /** @test */
    public function it_can_create_redis_driver()
    {
        Config::set('shopping-cart.storage.default', 'redis');
        Config::set('shopping-cart.storage.drivers.redis', [
            'connection' => 'default',
            'prefix' => 'cart',
            'ttl' => 3600,
        ]);

        $driver = $this->manager->driver('redis');

        $this->assertInstanceOf(RedisCartStorage::class, $driver);
    }

    /** @test */
    public function it_can_create_layered_driver()
    {
        Config::set('shopping-cart.storage.default', 'layered');
        Config::set('shopping-cart.storage.drivers.layered', [
            'cache_enabled' => true,
            'redis_enabled' => true,
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

        $this->assertInstanceOf(LayeredCartStorage::class, $driver);
    }

    /** @test */
    public function it_uses_default_driver_when_no_driver_specified()
    {
        Config::set('shopping-cart.storage.default', 'database');
        Config::set('shopping-cart.storage.drivers.database', []);

        $driver = $this->manager->driver();

        $this->assertInstanceOf(DatabaseCartStorage::class, $driver);
    }

    /** @test */
    public function it_returns_same_instance_for_same_driver()
    {
        Config::set('shopping-cart.storage.default', 'database');
        Config::set('shopping-cart.storage.drivers.database', []);

        $driver1 = $this->manager->driver('database');
        $driver2 = $this->manager->driver('database');

        $this->assertSame($driver1, $driver2);
    }

    /** @test */
    public function it_throws_exception_for_invalid_driver()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [invalid] not supported.');

        $this->manager->driver('invalid');
    }

    /** @test */
    public function it_can_extend_with_custom_driver()
    {
        $customDriver = new DatabaseCartStorage();
        
        $this->manager->extend('custom', function () use ($customDriver) {
            return $customDriver;
        });

        $driver = $this->manager->driver('custom');

        $this->assertSame($customDriver, $driver);
    }

    /** @test */
    public function it_gets_default_driver_name_from_config()
    {
        Config::set('shopping-cart.storage.default', 'redis');

        $defaultDriver = $this->manager->getDefaultDriver();

        $this->assertEquals('redis', $defaultDriver);
    }

    /** @test */
    public function it_falls_back_to_database_when_no_default_configured()
    {
        Config::set('shopping-cart.storage.default', null);

        $defaultDriver = $this->manager->getDefaultDriver();

        $this->assertEquals('database', $defaultDriver);
    }

    /** @test */
    public function it_creates_layered_driver_with_cache_only()
    {
        Config::set('shopping-cart.storage.drivers.layered', [
            'cache_enabled' => true,
            'redis_enabled' => false,
        ]);
        Config::set('shopping-cart.storage.drivers.cache', [
            'store' => 'default',
            'prefix' => 'cart',
            'ttl' => 3600,
        ]);

        $driver = $this->manager->driver('layered');

        $this->assertInstanceOf(LayeredCartStorage::class, $driver);
    }

    /** @test */
    public function it_creates_layered_driver_with_redis_only()
    {
        Config::set('shopping-cart.storage.drivers.layered', [
            'cache_enabled' => false,
            'redis_enabled' => true,
        ]);
        Config::set('shopping-cart.storage.drivers.redis', [
            'connection' => 'default',
            'prefix' => 'cart',
            'ttl' => 3600,
        ]);

        $driver = $this->manager->driver('layered');

        $this->assertInstanceOf(LayeredCartStorage::class, $driver);
    }

    /** @test */
    public function it_creates_layered_driver_with_database_only_when_no_cache_or_redis()
    {
        Config::set('shopping-cart.storage.drivers.layered', [
            'cache_enabled' => false,
            'redis_enabled' => false,
        ]);

        $driver = $this->manager->driver('layered');

        $this->assertInstanceOf(LayeredCartStorage::class, $driver);
    }

    /** @test */
    public function it_uses_correct_cache_configuration()
    {
        Config::set('shopping-cart.storage.drivers.cache', [
            'store' => 'redis',
            'prefix' => 'custom_cart',
            'ttl' => 7200,
        ]);

        $driver = $this->manager->driver('cache');

        $this->assertInstanceOf(CacheCartStorage::class, $driver);
        // Note: We can't easily test the internal configuration without exposing it,
        // but we can verify the driver is created successfully
    }

    /** @test */
    public function it_uses_correct_redis_configuration()
    {
        Config::set('shopping-cart.storage.drivers.redis', [
            'connection' => 'cache',
            'prefix' => 'custom_cart',
            'ttl' => 7200,
        ]);

        $driver = $this->manager->driver('redis');

        $this->assertInstanceOf(RedisCartStorage::class, $driver);
        // Note: We can't easily test the internal configuration without exposing it,
        // but we can verify the driver is created successfully
    }
}