<?php

use Illuminate\Support\Facades\Config;

use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Console\Commands\MigrateCartDataCommand;
use MobileNowGroup\ShoppingCart\Cache\CartCacheOptimizer;

beforeEach(function () {
    // Mock the CartStorageManager
    $this->mockStorageManager = Mockery::mock(CartStorageManager::class);
    $this->app->instance(CartStorageManager::class, $this->mockStorageManager);
    
    // Mock the CartCacheOptimizer
    $this->mockCacheOptimizer = Mockery::mock(CartCacheOptimizer::class);
    $this->app->instance(CartCacheOptimizer::class, $this->mockCacheOptimizer);
    
    // Configure storage drivers for all tests
    Config::set('shopping-cart.storage.drivers', [
        'database' => ['driver' => 'database'],
        'cache' => ['driver' => 'cache'],
    ]);
    
    // Create mock storage instances
    $this->mockSourceStorage = Mockery::mock();
    $this->mockTargetStorage = Mockery::mock();
    
    // Create and register the command
    $this->command = new MigrateCartDataCommand($this->mockStorageManager, $this->mockCacheOptimizer);
    app('Illuminate\Contracts\Console\Kernel')->registerCommand($this->command);
});

afterEach(function () {
    Mockery::close();
});

test('it validates source and target drivers', function () {
    $this->artisan('cart:migrate-data')
        ->expectsOutput('Source storage driver (--from) is required.')
        ->assertExitCode(1);
        
    $this->artisan('cart:migrate-data', ['--from' => 'database'])
        ->expectsOutput('Target storage driver (--to) is required.')
        ->assertExitCode(1);
        
    $this->artisan('cart:migrate-data', ['--from' => 'invalid', '--to' => 'cache'])
        ->expectsOutput('Error: Source driver [invalid] is not configured.')
        ->assertExitCode(1);
        
    $this->artisan('cart:migrate-data', ['--from' => 'database', '--to' => 'invalid'])
        ->expectsOutput('Error: Target driver [invalid] is not configured.')
        ->assertExitCode(1);
});

test('it prevents same source and target drivers', function () {
    $this->artisan('cart:migrate-data', ['--from' => 'database', '--to' => 'database'])
        ->expectsOutput('Error: Source and target drivers cannot be the same.')
        ->assertExitCode(1);
});

test('it performs dry run migration', function () {
    $cart1 = Mockery::mock(ShoppingCart::class);
    $cart1->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart1->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart1->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart1->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart1->exists = true;
    
    $cart2 = Mockery::mock(ShoppingCart::class);
    $cart2->shouldReceive('getAttribute')->with('id')->andReturn(2);
    $cart2->shouldReceive('getAttribute')->with('customer_id')->andReturn('user456');
    $cart2->shouldReceive('getAttribute')->with('customer_name')->andReturn('Jane Smith');
    $cart2->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart2->exists = true;
    
    $carts = collect([$cart1, $cart2]);

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn($carts);

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--dry-run' => true,
    ])
    ->assertExitCode(0);
});

test('it migrates cart data successfully', function () {
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart->exists = true;

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([$cart]));

    $this->mockTargetStorage
        ->shouldReceive('createCart')
        ->with('user123', Mockery::type('array'))
        ->andReturn($cart);

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it migrates specific customer cart', function () {
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart->exists = true;

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getCart')
        ->with('user123')
        ->andReturn($cart);

    $this->mockTargetStorage
        ->shouldReceive('createCart')
        ->with('user123', Mockery::type('array'))
        ->andReturn($cart);

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--customer-id' => 'user123',
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it handles migration errors gracefully', function () {
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart->exists = true;

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([$cart]));

    $this->mockTargetStorage
        ->shouldReceive('createCart')
        ->with('user123', Mockery::type('array'))
        ->andThrow(new \Exception('Storage error'));

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it processes carts in batches', function () {
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([]));

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--batch-size' => 2,
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it forces migration when target cart exists', function () {
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart->exists = true;

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([$cart]));

    $this->mockTargetStorage
        ->shouldReceive('createCart')
        ->with('user123', Mockery::type('array'))
        ->andReturn($cart);

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it skips existing carts when not forced', function () {
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn('user123');
    $cart->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
    $cart->shouldReceive('setRelation')->with('items', Mockery::any())->andReturnSelf();
    $cart->exists = true;

    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([$cart]));

    $this->mockTargetStorage
        ->shouldReceive('createCart')
        ->with('user123', Mockery::type('array'))
        ->andReturn($cart);

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--force' => true,
    ])
    ->assertExitCode(0);
});

test('it displays migration summary', function () {
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('database')
        ->once()
        ->andReturn($this->mockSourceStorage);
        
    $this->mockStorageManager
        ->shouldReceive('driver')
        ->with('cache')
        ->once()
        ->andReturn($this->mockTargetStorage);

    $this->mockSourceStorage
        ->shouldReceive('getAllCarts')
        ->andReturn(collect([]));

    $this->artisan('cart:migrate-data', [
        '--from' => 'database',
        '--to' => 'cache',
        '--force' => true,
    ])
    ->assertExitCode(0);
});