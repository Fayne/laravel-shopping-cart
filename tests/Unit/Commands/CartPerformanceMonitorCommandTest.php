<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

use MobileNowGroup\ShoppingCart\Console\Commands\CartPerformanceMonitorCommand;
use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockOptimizer = Mockery::mock(CartCacheOptimizer::class);
    $this->mockStorageManager = Mockery::mock(CartStorageManager::class);
    app()->instance(CartCacheOptimizer::class, $this->mockOptimizer);
    app()->instance(CartStorageManager::class, $this->mockStorageManager);
    
    $this->command = new CartPerformanceMonitorCommand($this->mockOptimizer, $this->mockStorageManager);
});

afterEach(function () {
    Mockery::close();
});

test('it generates performance report', function () {
    $stats = [
        'cache_hits' => 150,
        'cache_misses' => 50,
        'total_requests' => 200,
        'hit_rate' => 75.0,
        'avg_response_time' => 120.5,
        'slow_operations' => 5,
        'memory_usage' => [
            'peak' => 128,
            'average' => 96,
        ],
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([
            [
                'operation' => 'get_cart',
                'execution_time' => 150.0,
                'memory_usage' => 64,
                'timestamp' => now()->subMinutes(10)->timestamp,
            ],
            [
                'operation' => 'add_item',
                'execution_time' => 80.0,
                'memory_usage' => 32,
                'timestamp' => now()->subMinutes(5)->timestamp,
            ],
        ]);

    $this->artisan('cart:performance-monitor', ['--report' => true])
        ->expectsOutput('Cart Performance Report')
        ->expectsOutput('Cache Statistics:')
        ->expectsOutput('- Cache Hits: 150')
        ->expectsOutput('- Cache Misses: 50')
        ->expectsOutput('- Hit Rate: 75.00%')
        ->expectsOutput('Operation Performance:')
        ->expectsOutput('- Total Operations: 2')
        ->expectsOutput('- Average Response Time: 115.00ms')
        ->expectsOutput('- Slow Operations (>100ms): 1')
        ->expectsOutput('Memory Usage:')
        ->expectsOutput('- Peak Memory: 128MB')
        ->expectsOutput('- Average Memory: 96MB')
        ->assertExitCode(0);
});

test('it analyzes performance issues', function () {
    $stats = [
        'cache_hits' => 50,
        'cache_misses' => 150,
        'total_requests' => 200,
        'hit_rate' => 25.0,
        'avg_response_time' => 250.5,
        'slow_operations' => 15,
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([
            [
                'operation' => 'get_cart',
                'execution_time' => 300.0,
                'memory_usage' => 256,
                'timestamp' => now()->subMinutes(10)->timestamp,
            ],
        ]);

    $this->artisan('cart:performance-monitor', ['--analyze' => true])
        ->expectsOutput('Performance Analysis')
        ->expectsOutput('Issues Found:')
        ->expectsOutput('⚠️  Low cache hit rate: 25.00% (recommended: >80%)')
        ->expectsOutput('⚠️  High average response time: 250.50ms (recommended: <100ms)')
        ->expectsOutput('⚠️  High number of slow operations: 15 (recommended: <5)')
        ->expectsOutput('⚠️  High memory usage detected: 256MB (recommended: <128MB)')
        ->expectsOutput('Recommendations:')
        ->expectsOutput('- Increase cache TTL or optimize cache strategy')
        ->expectsOutput('- Review and optimize slow database queries')
        ->expectsOutput('- Consider implementing query result caching')
        ->expectsOutput('- Optimize memory usage in cart operations')
        ->assertExitCode(0);
});

test('it shows no issues when performance is good', function () {
    $stats = [
        'cache_hits' => 180,
        'cache_misses' => 20,
        'total_requests' => 200,
        'hit_rate' => 90.0,
        'avg_response_time' => 50.5,
        'slow_operations' => 2,
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([
            [
                'operation' => 'get_cart',
                'execution_time' => 45.0,
                'memory_usage' => 64,
                'timestamp' => now()->subMinutes(10)->timestamp,
            ],
        ]);

    $this->artisan('cart:performance-monitor', ['--analyze' => true])
        ->expectsOutput('Performance Analysis')
        ->expectsOutput('✅ No performance issues detected!')
        ->expectsOutput('Your cart system is performing well.')
        ->assertExitCode(0);
});

test('it cleans old monitoring data', function () {
    $this->mockOptimizer
        ->shouldReceive('cleanupExpiredMonitoringData')
        ->once()
        ->andReturn(50);

    $this->artisan('cart:performance-monitor', ['--cleanup' => true])
        ->expectsOutput('Cleaning up old monitoring data...')
        ->expectsOutput('Removed 50 expired monitoring records.')
        ->expectsOutput('Cleanup completed successfully!')
        ->assertExitCode(0);
});

test('it runs benchmark tests', function () {
    Config::set('shopping-cart.storage.default', 'database');
    
    $this->artisan('cart:performance-monitor', ['--benchmark' => true])
        ->expectsOutput('Running Cart Performance Benchmark')
        ->expectsOutput('Testing cart operations...')
        ->expectsOutput('Benchmark Results:')
        ->expectsOutput('- Create Cart:')
        ->expectsOutput('- Add Item:')
        ->expectsOutput('- Get Cart:')
        ->expectsOutput('- Update Item:')
        ->expectsOutput('- Remove Item:')
        ->expectsOutput('- Delete Cart:')
        ->expectsOutput('Benchmark completed!')
        ->assertExitCode(0);
});

test('it handles custom days parameter', function () {
    $stats = [
        'cache_hits' => 100,
        'cache_misses' => 50,
        'total_requests' => 150,
        'hit_rate' => 66.67,
        'avg_response_time' => 85.0,
        'slow_operations' => 3,
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([]);

    $this->artisan('cart:performance-monitor', [
        '--report' => true,
        '--days' => 7,
    ])
    ->expectsOutput('Cart Performance Report (Last 7 days)')
    ->expectsOutput('Cache Statistics:')
    ->assertExitCode(0);
});

test('it validates days parameter', function () {
    $this->artisan('cart:performance-monitor', [
        '--report' => true,
        '--days' => 0,
    ])
    ->expectsOutput('Error: Days must be a positive integer.')
    ->assertExitCode(1);

    $this->artisan('cart:performance-monitor', [
        '--report' => true,
        '--days' => 91,
    ])
    ->expectsOutput('Error: Days cannot exceed 90.')
    ->assertExitCode(1);
});

test('it requires at least one option', function () {
    $this->artisan('cart:performance-monitor')
        ->expectsOutput('Please specify at least one option: --report, --analyze, --cleanup, or --benchmark')
        ->assertExitCode(1);
});

test('it handles empty performance data', function () {
    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn([]);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([]);

    $this->artisan('cart:performance-monitor', ['--report' => true])
        ->expectsOutput('Cart Performance Report')
        ->expectsOutput('No performance data available.')
        ->assertExitCode(0);
});

test('it formats execution time correctly', function () {
    $stats = [
        'cache_hits' => 100,
        'cache_misses' => 0,
        'total_requests' => 100,
        'hit_rate' => 100.0,
        'avg_response_time' => 1250.75,
        'slow_operations' => 0,
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([
            [
                'operation' => 'get_cart',
                'execution_time' => 1500.0,
                'memory_usage' => 64,
                'timestamp' => now()->subMinutes(10)->timestamp,
            ],
        ]);

    $this->artisan('cart:performance-monitor', ['--report' => true])
        ->expectsOutput('- Average Response Time: 1500.00ms')
        ->assertExitCode(0);
});

test('it handles benchmark errors gracefully', function () {
    Config::set('shopping-cart.storage.default', 'invalid_driver');
    
    $this->artisan('cart:performance-monitor', ['--benchmark' => true])
        ->expectsOutput('Running Cart Performance Benchmark')
        ->expectsOutput('Error during benchmark:')
        ->assertExitCode(1);
});

test('it shows detailed operation breakdown', function () {
    $stats = [
        'cache_hits' => 100,
        'cache_misses' => 50,
        'total_requests' => 150,
        'hit_rate' => 66.67,
        'avg_response_time' => 85.0,
        'slow_operations' => 3,
    ];

    $this->mockOptimizer
        ->shouldReceive('getCacheStats')
        ->andReturn($stats);

    Cache::shouldReceive('get')
        ->with('cart_performance_operations', [])
        ->andReturn([
            [
                'operation' => 'get_cart',
                'execution_time' => 150.0,
                'memory_usage' => 64,
                'timestamp' => now()->subMinutes(10)->timestamp,
            ],
            [
                'operation' => 'get_cart',
                'execution_time' => 80.0,
                'memory_usage' => 32,
                'timestamp' => now()->subMinutes(5)->timestamp,
            ],
            [
                'operation' => 'add_item',
                'execution_time' => 120.0,
                'memory_usage' => 48,
                'timestamp' => now()->subMinutes(3)->timestamp,
            ],
        ]);

    $this->artisan('cart:performance-monitor', ['--report' => true])
        ->expectsOutput('Operation Breakdown:')
        ->expectsOutput('- get_cart: 2 operations, avg 115.00ms')
        ->expectsOutput('- add_item: 1 operations, avg 120.00ms')
        ->assertExitCode(0);
});