<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use MobileNowGroup\ShoppingCart\Commands\MigrateCartDataCommand;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class MigrateCartDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $command;
    protected $mockStorageManager;
    protected $mockSourceStorage;
    protected $mockTargetStorage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockStorageManager = Mockery::mock(CartStorageManager::class);
        $this->mockSourceStorage = Mockery::mock(CartStorageInterface::class);
        $this->mockTargetStorage = Mockery::mock(CartStorageInterface::class);
        
        $this->app->instance(CartStorageManager::class, $this->mockStorageManager);
        
        $this->command = new MigrateCartDataCommand();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_source_and_target_drivers()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $this->artisan('cart:migrate-data', [
            '--source' => 'invalid',
            '--target' => 'database',
        ])
        ->expectsOutput('Error: Source driver [invalid] is not configured.')
        ->assertExitCode(1);
    }

    /** @test */
    public function it_prevents_same_source_and_target_drivers()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'database',
        ])
        ->expectsOutput('Error: Source and target drivers cannot be the same.')
        ->assertExitCode(1);
    }

    /** @test */
    public function it_performs_dry_run_migration()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $carts = collect([
            new ShoppingCart([
                'id' => 1,
                'customer_id' => 'user123',
                'customer_name' => 'John Doe',
            ]),
            new ShoppingCart([
                'id' => 2,
                'customer_id' => 'user456',
                'customer_name' => 'Jane Doe',
            ]),
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn($carts);

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
            '--dry-run' => true,
        ])
        ->expectsOutput('Starting cart data migration (DRY RUN)')
        ->expectsOutput('Found 2 carts to migrate')
        ->expectsOutput('DRY RUN: Would migrate cart for customer: user123')
        ->expectsOutput('DRY RUN: Would migrate cart for customer: user456')
        ->expectsOutput('DRY RUN completed. 2 carts would be migrated.')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_migrates_cart_data_successfully()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $items = collect([
            new ShoppingCartItem([
                'id' => 1,
                'cart_id' => 1,
                'product_id' => 1,
                'quantity' => 2,
                'price' => 10.00,
                'name' => 'Test Product',
            ])
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn(collect([$cart]));
            
        $this->mockSourceStorage
            ->shouldReceive('getCartItems')
            ->with($cart->id)
            ->andReturn($items);

        $this->mockTargetStorage
            ->shouldReceive('createCart')
            ->with('user123', ['customer_name' => 'John Doe'])
            ->andReturn($cart);
            
        $this->mockTargetStorage
            ->shouldReceive('addItem')
            ->with($cart->id, [
                'product_id' => 1,
                'quantity' => 2,
                'price' => 10.00,
                'name' => 'Test Product',
                'options' => null,
            ])
            ->andReturn($items->first());

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
            '--batch-size' => 10,
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 1 carts to migrate')
        ->expectsOutput('Migrated cart for customer: user123')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_migrates_specific_customer_cart()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getCart')
            ->with('user123')
            ->andReturn($cart);
            
        $this->mockSourceStorage
            ->shouldReceive('getCartItems')
            ->with($cart->id)
            ->andReturn(collect([]));

        $this->mockTargetStorage
            ->shouldReceive('createCart')
            ->with('user123', ['customer_name' => 'John Doe'])
            ->andReturn($cart);

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
            '--customer' => 'user123',
        ])
        ->expectsOutput('Starting cart data migration for customer: user123')
        ->expectsOutput('Migrated cart for customer: user123')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_migration_errors_gracefully()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn(collect([$cart]));
            
        $this->mockSourceStorage
            ->shouldReceive('getCartItems')
            ->with($cart->id)
            ->andReturn(collect([]));

        $this->mockTargetStorage
            ->shouldReceive('createCart')
            ->with('user123', ['customer_name' => 'John Doe'])
            ->andThrow(new \Exception('Migration failed'));

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 1 carts to migrate')
        ->expectsOutput('Failed to migrate cart for customer: user123. Error: Migration failed')
        ->expectsOutput('Migration completed with errors.')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_carts_in_batches()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $carts = collect();
        for ($i = 1; $i <= 25; $i++) {
            $carts->push(new ShoppingCart([
                'id' => $i,
                'customer_id' => "user{$i}",
                'customer_name' => "User {$i}",
            ]));
        }

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn($carts);
            
        $this->mockSourceStorage
            ->shouldReceive('getCartItems')
            ->times(25)
            ->andReturn(collect([]));

        $this->mockTargetStorage
            ->shouldReceive('createCart')
            ->times(25)
            ->andReturn(new ShoppingCart());

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
            '--batch-size' => 10,
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 25 carts to migrate')
        ->expectsOutput('Processing batch 1/3 (10 carts)')
        ->expectsOutput('Processing batch 2/3 (10 carts)')
        ->expectsOutput('Processing batch 3/3 (5 carts)')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_forces_migration_when_target_cart_exists()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn(collect([$cart]));
            
        $this->mockSourceStorage
            ->shouldReceive('getCartItems')
            ->with($cart->id)
            ->andReturn(collect([]));

        $this->mockTargetStorage
            ->shouldReceive('getCart')
            ->with('user123')
            ->andReturn($cart); // Cart already exists
            
        $this->mockTargetStorage
            ->shouldReceive('deleteCart')
            ->with('user123')
            ->andReturn(true);
            
        $this->mockTargetStorage
            ->shouldReceive('createCart')
            ->with('user123', ['customer_name' => 'John Doe'])
            ->andReturn($cart);

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
            '--force' => true,
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 1 carts to migrate')
        ->expectsOutput('Migrated cart for customer: user123')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_existing_carts_when_not_forced()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => 'user123',
            'customer_name' => 'John Doe',
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);
            
        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('cache')
            ->andReturn($this->mockTargetStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn(collect([$cart]));

        $this->mockTargetStorage
            ->shouldReceive('getCart')
            ->with('user123')
            ->andReturn($cart); // Cart already exists

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 1 carts to migrate')
        ->expectsOutput('Skipped cart for customer: user123 (already exists)')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_migration_summary()
    {
        Config::set('shopping-cart.storage.drivers', [
            'database' => [],
            'cache' => [],
        ]);

        $this->mockStorageManager
            ->shouldReceive('driver')
            ->with('database')
            ->andReturn($this->mockSourceStorage);

        $this->mockSourceStorage
            ->shouldReceive('getAllCarts')
            ->andReturn(collect([]));

        $this->artisan('cart:migrate-data', [
            '--source' => 'database',
            '--target' => 'cache',
        ])
        ->expectsOutput('Starting cart data migration')
        ->expectsOutput('Found 0 carts to migrate')
        ->expectsOutput('Migration Summary:')
        ->expectsOutput('- Total carts: 0')
        ->expectsOutput('- Migrated: 0')
        ->expectsOutput('- Skipped: 0')
        ->expectsOutput('- Failed: 0')
        ->expectsOutput('Migration completed successfully!')
        ->assertExitCode(0);
    }
}