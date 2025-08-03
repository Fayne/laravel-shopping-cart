<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Traits\CartPerformanceMonitoring;

uses(RefreshDatabase::class);

beforeEach(function () {
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
});

test('it monitors operation performance', function () {
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

    expect($result)->toBe('test_result');
});

test('it caches performance stats', function () {
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

    expect($result)->toBe('test_result');
});

test('it detects slow operations', function () {
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

    expect($result)->toBe('slow_result');
});

test('it detects high memory usage', function () {
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

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Cart operation failed') &&
                   isset($context['operation']) &&
                   $context['operation'] === 'failing_operation' &&
                   isset($context['error']) &&
                   $context['error'] === 'Test exception';
        });

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

    expect($result)->toBe('result');
});

test('it handles cache failures gracefully', function () {
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
    expect($key1)->toContain('cart:performance:operation1');
    expect($key2)->toContain('cart:performance:operation2');
});

test('it includes timestamp in performance data', function () {
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
});