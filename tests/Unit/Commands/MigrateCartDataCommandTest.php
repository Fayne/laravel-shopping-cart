<?php

use Illuminate\Support\Facades\Config;

use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;

beforeEach(function () {
    // Mock the CartStorageManager
    $this->mockStorageManager = Mockery::mock(CartStorageManager::class);
    $this->app->instance(CartStorageManager::class, $this->mockStorageManager);
    
    // Configure storage drivers for all tests
    Config::set('shopping-cart.storage.drivers', [
        'database' => ['driver' => 'database'],
        'cache' => ['driver' => 'cache'],
    ]);
    
    // Create mock storage instances
    $this->mockSourceStorage = Mockery::mock();
    $this->mockTargetStorage = Mockery::mock();
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
    $carts = collect([
        new ShoppingCart(['id' => 1, 'customer_id' => 'user123', 'customer_name' => 'John Doe']),
        new ShoppingCart(['id' => 2, 'customer_id' => 'user456', 'customer_name' => 'Jane Smith']),
    ]);
    
    // Set up relations for both carts
    $carts->each(function ($cart) {
        $cart->setRelation('items', collect([]));
        $cart->exists = true;
    });

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
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $cart->setRelation('items', collect([]));
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
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $cart->setRelation('items', collect([]));
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
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $cart->setRelation('items', collect([]));
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
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $cart->setRelation('items', collect([]));
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
    $cart = new ShoppingCart([
        'id' => 1,
        'customer_id' => 'user123',
        'customer_name' => 'John Doe',
    ]);
    $cart->setRelation('items', collect([]));
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