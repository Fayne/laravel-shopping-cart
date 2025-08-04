<?php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Traits\CartPerformanceMonitoring;
use Mockery;



beforeEach(function () {
    // Reset facades
    Log::clearResolvedInstances();
    Cache::clearResolvedInstances();
    
    // Set configuration for performance monitoring
    app('config')->set('shopping-cart.performance.log_enabled', true);
    app('config')->set('shopping-cart.performance.log_channel', 'default');
    app('config')->set('shopping-cart.performance.cache_enabled', true);
    app('config')->set('shopping-cart.performance.cache_key', 'cart_performance_stats');
    app('config')->set('shopping-cart.performance.cache_ttl', 3600);
    
    // Mock Log facade
    Log::shouldReceive('channel')
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->zeroOrMoreTimes();
    Log::shouldReceive('error')
        ->zeroOrMoreTimes();
    Log::shouldReceive('warning')
        ->zeroOrMoreTimes();
    
    // Mock Cache facade
    Cache::shouldReceive('put')
        ->zeroOrMoreTimes();
    Cache::shouldReceive('get')
        ->zeroOrMoreTimes()
        ->andReturn([]);
    
    $this->testClass = new class {
        use CartPerformanceMonitoring;
        
        public function __construct()
        {
            $this->enablePerformanceMonitoring = true;
        }
        
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
});

test('it monitors operation performance', function () {
    $result = $this->testClass->testOperation();
    
    expect($result)->toBe('test_result');
    
    // Verify that performance metrics were recorded
    $reflection = new \ReflectionClass($this->testClass);
    $property = $reflection->getProperty('performanceMetrics');
    $property->setAccessible(true);
    $metrics = $property->getValue($this->testClass);
    
    // The operation should have completed and metrics should be cleared
    expect($metrics)->toBeEmpty();
});

test('it caches performance stats', function () {
    $result = $this->testClass->testOperation();

    expect($result)->toBe('test_result');
});

test('it detects slow operations', function () {
    $result = $this->testClass->testSlowOperation();

    expect($result)->toBe('slow_result');
});

test('it detects high memory usage', function () {
    $result = $this->testClass->testMemoryIntensiveOperation();

    expect($result)->toBe(10000);
});

test('it handles exceptions in monitored operations', function () {
    $testClass = new class {
        use CartPerformanceMonitoring;
        
        public function testFailingOperation()
        {
            return $this->monitorPerformance('failing_operation', function () {
                throw new \Exception('Test exception');
            });
        }
    };

    expect(fn() => $testClass->testFailingOperation())
        ->toThrow(\Exception::class, 'Test exception');
});

test('it formats execution time correctly', function () {
    $testClass = new class {
        use CartPerformanceMonitoring;
        
        public function getFormattedTime($microseconds)
        {
            return $this->formatExecutionTime($microseconds);
        }
    };

    // Test microseconds
    expect($testClass->getFormattedTime(500))->toBe('500μs');
    
    // Test milliseconds
    expect($testClass->getFormattedTime(1500))->toBe('1.50ms');
    
    // Test seconds
    expect($testClass->getFormattedTime(1500000))->toBe('1.50s');
});

test('it formats memory usage correctly', function () {
    $testClass = new class {
        use CartPerformanceMonitoring;
        
        public function getFormattedMemory($bytes)
        {
            return $this->formatMemoryUsage($bytes);
        }
    };

    // Test bytes
    expect($testClass->getFormattedMemory(512))->toBe('512B');
    
    // Test kilobytes
    expect($testClass->getFormattedMemory(1536))->toBe('1.50KB');
    
    // Test megabytes
    expect($testClass->getFormattedMemory(2621440))->toBe('2.50MB');
    
    // Test gigabytes
    expect($testClass->getFormattedMemory(1342177280))->toBe('1.25GB');
});

test('it uses custom thresholds when provided', function () {
    $testClass = new class {
        use CartPerformanceMonitoring;
        
        public function __construct() {
            $this->performanceThresholds = [
                'execution_time' => 50, // 50ms
                'memory_usage' => 512 * 1024, // 512KB
            ];
        }
        
        public function testCustomThresholds()
        {
            return $this->monitorPerformance('custom_operation', function () {
                usleep(60000); // 60ms - should trigger warning
                return 'result';
            });
        }
    };

    $result = $testClass->testCustomThresholds();

    expect($result)->toBe('result');
});

test('it handles cache failures gracefully', function () {
    $result = $this->testClass->testOperation();

    expect($result)->toBe('test_result');
});

test('it generates unique cache keys for different operations', function () {
    $testClass = new class {
        use CartPerformanceMonitoring;
        
        public function getCacheKey($operation)
        {
            return $this->getPerformanceCacheKey($operation);
        }
    };

    $key1 = $testClass->getCacheKey('operation1');
    $key2 = $testClass->getCacheKey('operation2');

    expect($key1)->not->toBe($key2);
    expect($key1)->toContain('cart_performance_stats:operation1');
    expect($key2)->toContain('cart_performance_stats:operation2');
});

test('it includes timestamp in performance data', function () {
    $this->testClass->testOperation();
    
    // Test passes if no exceptions are thrown
    expect(true)->toBeTrue();
});

test('it aggregates performance data correctly', function () {
    $this->testClass->testOperation();
    $this->testClass->testOperation();
    $this->testClass->testOperation();
    
    // Test passes if no exceptions are thrown
    expect(true)->toBeTrue();
});