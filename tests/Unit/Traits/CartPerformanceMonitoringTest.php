<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Traits\CartPerformanceMonitoring;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CartPerformanceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected $testClass;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testClass = new class {
            use CartPerformanceMonitoring;
            
            public function testOperation()
            {
                return $this->monitorPerformance('test_operation', function () {
                    // Simulate some work
                    usleep(1000); // 1ms
                    return 'test_result';
                });
            }
            
            public function testSlowOperation()
            {
                return $this->monitorPerformance('slow_operation', function () {
                    // Simulate slow work
                    usleep(150000); // 150ms
                    return 'slow_result';
                });
            }
            
            public function testMemoryIntensiveOperation()
            {
                return $this->monitorPerformance('memory_operation', function () {
                    // Simulate memory usage
                    $data = array_fill(0, 10000, 'test');
                    return count($data);
                });
            }
        };
    }

    /** @test */
    public function it_monitors_operation_performance()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Cart operation performance') &&
                       isset($context['operation']) &&
                       $context['operation'] === 'test_operation' &&
                       isset($context['execution_time']) &&
                       isset($context['memory_used']) &&
                       isset($context['peak_memory']);
            });

        $result = $this->testClass->testOperation();

        $this->assertEquals('test_result', $result);
    }

    /** @test */
    public function it_caches_performance_stats()
    {
        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $data, $ttl) {
                return str_contains($key, 'cart:performance:test_operation') &&
                       is_array($data) &&
                       isset($data['execution_time']) &&
                       isset($data['memory_used']) &&
                       isset($data['peak_memory']) &&
                       isset($data['timestamp']) &&
                       $ttl === 3600;
            })
            ->andReturn(true);
            
        Log::shouldReceive('info')->once();

        $result = $this->testClass->testOperation();

        $this->assertEquals('test_result', $result);
    }

    /** @test */
    public function it_detects_slow_operations()
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Slow cart operation detected') &&
                       isset($context['operation']) &&
                       $context['operation'] === 'slow_operation' &&
                       isset($context['execution_time']) &&
                       $context['execution_time'] > 100; // 100ms threshold
            });
            
        Log::shouldReceive('info')->once(); // Regular performance log
        Cache::shouldReceive('store')->once()->andReturnSelf();
        Cache::shouldReceive('put')->once()->andReturn(true);

        $result = $this->testClass->testSlowOperation();

        $this->assertEquals('slow_result', $result);
    }

    /** @test */
    public function it_detects_high_memory_usage()
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'High memory usage detected') &&
                       isset($context['operation']) &&
                       $context['operation'] === 'memory_operation' &&
                       isset($context['memory_used']) &&
                       $context['memory_used'] > 1048576; // 1MB threshold
            });
            
        Log::shouldReceive('info')->once(); // Regular performance log
        Cache::shouldReceive('store')->once()->andReturnSelf();
        Cache::shouldReceive('put')->once()->andReturn(true);

        $result = $this->testClass->testMemoryIntensiveOperation();

        $this->assertEquals(10000, $result);
    }

    /** @test */
    public function it_handles_exceptions_in_monitored_operations()
    {
        $testClass = new class {
            use CartPerformanceMonitoring;
            
            public function testFailingOperation()
            {
                return $this->monitorPerformance('failing_operation', function () {
                    throw new \Exception('Test exception');
                });
            }
        };

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Cart operation failed') &&
                       isset($context['operation']) &&
                       $context['operation'] === 'failing_operation' &&
                       isset($context['error']) &&
                       $context['error'] === 'Test exception';
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $testClass->testFailingOperation();
    }

    /** @test */
    public function it_formats_execution_time_correctly()
    {
        $testClass = new class {
            use CartPerformanceMonitoring;
            
            public function getFormattedTime($microseconds)
            {
                return $this->formatExecutionTime($microseconds);
            }
        };

        // Test microseconds
        $this->assertEquals('500μs', $testClass->getFormattedTime(500));
        
        // Test milliseconds
        $this->assertEquals('1.50ms', $testClass->getFormattedTime(1500));
        
        // Test seconds
        $this->assertEquals('1.50s', $testClass->getFormattedTime(1500000));
    }

    /** @test */
    public function it_formats_memory_usage_correctly()
    {
        $testClass = new class {
            use CartPerformanceMonitoring;
            
            public function getFormattedMemory($bytes)
            {
                return $this->formatMemoryUsage($bytes);
            }
        };

        // Test bytes
        $this->assertEquals('512B', $testClass->getFormattedMemory(512));
        
        // Test kilobytes
        $this->assertEquals('1.50KB', $testClass->getFormattedMemory(1536));
        
        // Test megabytes
        $this->assertEquals('2.50MB', $testClass->getFormattedMemory(2621440));
        
        // Test gigabytes
        $this->assertEquals('1.25GB', $testClass->getFormattedMemory(1342177280));
    }

    /** @test */
    public function it_uses_custom_thresholds_when_provided()
    {
        $testClass = new class {
            use CartPerformanceMonitoring;
            
            protected $performanceThresholds = [
                'execution_time' => 50, // 50ms
                'memory_usage' => 512 * 1024, // 512KB
            ];
            
            public function testCustomThresholds()
            {
                return $this->monitorPerformance('custom_operation', function () {
                    usleep(60000); // 60ms - should trigger warning
                    return 'result';
                });
            }
        };

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Slow cart operation detected') &&
                       $context['operation'] === 'custom_operation';
            });
            
        Log::shouldReceive('info')->once();
        Cache::shouldReceive('store')->once()->andReturnSelf();
        Cache::shouldReceive('put')->once()->andReturn(true);

        $result = $testClass->testCustomThresholds();

        $this->assertEquals('result', $result);
    }

    /** @test */
    public function it_handles_cache_failures_gracefully()
    {
        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andThrow(new \Exception('Cache connection failed'));
            
        Log::shouldReceive('info')->once(); // Performance log should still work
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to cache performance data') &&
                       isset($context['operation']) &&
                       $context['operation'] === 'test_operation';
            });

        $result = $this->testClass->testOperation();

        $this->assertEquals('test_result', $result);
    }

    /** @test */
    public function it_generates_unique_cache_keys_for_different_operations()
    {
        $testClass = new class {
            use CartPerformanceMonitoring;
            
            public function getCacheKey($operation)
            {
                return $this->getPerformanceCacheKey($operation);
            }
        };

        $key1 = $testClass->getCacheKey('operation1');
        $key2 = $testClass->getCacheKey('operation2');

        $this->assertNotEquals($key1, $key2);
        $this->assertStringContainsString('cart:performance:operation1', $key1);
        $this->assertStringContainsString('cart:performance:operation2', $key2);
    }

    /** @test */
    public function it_includes_timestamp_in_performance_data()
    {
        $beforeTime = time();
        
        Cache::shouldReceive('store')
            ->once()
            ->andReturnSelf();
            
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $data, $ttl) use ($beforeTime) {
                $afterTime = time();
                return isset($data['timestamp']) &&
                       $data['timestamp'] >= $beforeTime &&
                       $data['timestamp'] <= $afterTime;
            })
            ->andReturn(true);
            
        Log::shouldReceive('info')->once();

        $this->testClass->testOperation();
    }
}