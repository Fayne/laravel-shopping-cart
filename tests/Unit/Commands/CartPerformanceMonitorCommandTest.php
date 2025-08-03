<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use MobileNowGroup\ShoppingCart\Commands\CartPerformanceMonitorCommand;
use MobileNowGroup\ShoppingCart\Optimization\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartPerformanceMonitorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $command;
    protected $mockOptimizer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockOptimizer = Mockery::mock(CartCacheOptimizer::class);
        $this->app->instance(CartCacheOptimizer::class, $this->mockOptimizer);
        
        $this->command = new CartPerformanceMonitorCommand();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_performance_report()
    {
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
    }

    /** @test */
    public function it_analyzes_performance_issues()
    {
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
    }

    /** @test */
    public function it_shows_no_issues_when_performance_is_good()
    {
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
    }

    /** @test */
    public function it_cleans_old_monitoring_data()
    {
        $this->mockOptimizer
            ->shouldReceive('cleanupExpiredMonitoringData')
            ->once()
            ->andReturn(50);

        $this->artisan('cart:performance-monitor', ['--cleanup' => true])
            ->expectsOutput('Cleaning up old monitoring data...')
            ->expectsOutput('Removed 50 expired monitoring records.')
            ->expectsOutput('Cleanup completed successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_benchmark_tests()
    {
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
    }

    /** @test */
    public function it_handles_custom_days_parameter()
    {
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
    }

    /** @test */
    public function it_validates_days_parameter()
    {
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
    }

    /** @test */
    public function it_requires_at_least_one_option()
    {
        $this->artisan('cart:performance-monitor')
            ->expectsOutput('Please specify at least one option: --report, --analyze, --cleanup, or --benchmark')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_empty_performance_data()
    {
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
    }

    /** @test */
    public function it_formats_execution_time_correctly()
    {
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
    }

    /** @test */
    public function it_handles_benchmark_errors_gracefully()
    {
        Config::set('shopping-cart.storage.default', 'invalid_driver');
        
        $this->artisan('cart:performance-monitor', ['--benchmark' => true])
            ->expectsOutput('Running Cart Performance Benchmark')
            ->expectsOutput('Error during benchmark:')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_shows_detailed_operation_breakdown()
    {
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
    }
}