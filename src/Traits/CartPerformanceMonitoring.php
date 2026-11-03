<?php

namespace Fayne\ShoppingCart\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 购物车性能监控trait
 * 用于监控和记录购物车操作的性能指标
 */
trait CartPerformanceMonitoring
{
    protected array $performanceMetrics = [];
    protected bool $enablePerformanceMonitoring = true;
    protected array $performanceThresholds = [];

    /**
     * 开始性能监控
     *
     * @param string $operation
     * @return void
     */
    protected function startPerformanceMonitoring(string $operation): void
    {
        if (!$this->enablePerformanceMonitoring) {
            return;
        }

        $this->performanceMetrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'operation' => $operation,
        ];
    }

    /**
     * 结束性能监控
     *
     * @param string $operation
     * @param array $additionalData
     * @return void
     */
    protected function endPerformanceMonitoring(string $operation, array $additionalData = []): void
    {
        if (!$this->enablePerformanceMonitoring || !isset($this->performanceMetrics[$operation])) {
            return;
        }

        $metrics = $this->performanceMetrics[$operation];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $performanceData = [
            'operation' => $operation,
            'execution_time' => round(($endTime - $metrics['start_time']) * 1000, 2), // ms
            'memory_usage' => $endMemory - $metrics['start_memory'], // bytes
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => now()->toISOString(),
        ] + $additionalData;

        // 记录性能数据
        $this->recordPerformanceMetrics($performanceData);

        // 清理已完成的监控数据
        unset($this->performanceMetrics[$operation]);
    }

    /**
     * 记录性能指标
     *
     * @param array $data
     * @return void
     */
    protected function recordPerformanceMetrics(array $data): void
    {
        // 记录到日志
        if (config('shopping-cart.performance.log_enabled', false)) {
            Log::channel(config('shopping-cart.performance.log_channel', 'default'))
                ->info('Cart Performance Metrics', $data);
        }

        // 缓存性能统计
        if (config('shopping-cart.performance.cache_enabled', false)) {
            $this->cachePerformanceStats($data);
        }

        // 检查性能阈值
        $this->checkPerformanceThresholds($data);
    }

    /**
     * 缓存性能统计数据
     *
     * @param array $data
     * @return void
     */
    protected function cachePerformanceStats(array $data): void
    {
        try {
            $cacheKey = 'cart_performance_stats_' . date('Y-m-d-H');
            $stats = Cache::get($cacheKey, []);
            
            $operation = $data['operation'];
            if (!isset($stats[$operation])) {
                $stats[$operation] = [
                    'count' => 0,
                    'total_time' => 0,
                    'max_time' => 0,
                    'min_time' => PHP_FLOAT_MAX,
                    'total_memory' => 0,
                    'max_memory' => 0,
                ];
            }
            
            $stats[$operation]['count']++;
            $stats[$operation]['total_time'] += $data['execution_time'];
            $stats[$operation]['max_time'] = max($stats[$operation]['max_time'], $data['execution_time']);
            $stats[$operation]['min_time'] = min($stats[$operation]['min_time'], $data['execution_time']);
            $stats[$operation]['total_memory'] += $data['memory_usage'];
            $stats[$operation]['max_memory'] = max($stats[$operation]['max_memory'], $data['memory_usage']);
            
            // 计算平均值
            $stats[$operation]['avg_time'] = $stats[$operation]['total_time'] / $stats[$operation]['count'];
            $stats[$operation]['avg_memory'] = $stats[$operation]['total_memory'] / $stats[$operation]['count'];
            
            Cache::store('default')->put($cacheKey, $stats, now()->addHours(25)); // 保存25小时
        } catch (\Exception $e) {
            Log::error('Failed to cache performance data', [
                'operation' => $data['operation'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查性能阈值
     *
     * @param array $data
     * @return void
     */
    protected function checkPerformanceThresholds(array $data): void
    {
        $thresholds = config('shopping-cart.performance.thresholds', []);
        
        if (empty($thresholds)) {
            return;
        }

        $operation = $data['operation'];
        $executionTime = $data['execution_time'];
        $memoryUsage = $data['memory_usage'];

        // 获取执行时间阈值（优先使用操作特定阈值，否则使用默认阈值）
        $executionTimeThreshold = $thresholds['execution_time'][$operation] ?? 
                                 $thresholds['execution_time']['default'] ?? 
                                 ($this->performanceThresholds['execution_time'] ?? 100);
        
        // 获取内存使用阈值（优先使用操作特定阈值，否则使用默认阈值）
        $memoryUsageThreshold = $thresholds['memory_usage'][$operation] ?? 
                               $thresholds['memory_usage']['default'] ?? 
                               ($this->performanceThresholds['memory_usage'] ?? 1048576);

        // 检查执行时间阈值
        if ($executionTime > $executionTimeThreshold) {
            Log::warning('Slow cart operation detected', [
                'operation' => $operation,
                'execution_time' => $executionTime,
                'threshold' => $executionTimeThreshold,
            ]);
        }

        // 检查内存使用阈值
        if ($memoryUsage > $memoryUsageThreshold) {
            Log::warning('High memory usage detected in cart operation', [
                'operation' => $operation,
                'memory_usage' => $memoryUsage,
                'threshold' => $memoryUsageThreshold,
            ]);
        }
    }

    /**
     * 获取性能统计数据
     *
     * @param string|null $date
     * @return array
     */
    public function getPerformanceStats(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d-H');
        $cacheKey = 'cart_performance_stats_' . $date;
        
        return Cache::get($cacheKey, []);
    }

    /**
     * 清理性能统计数据
     *
     * @param int $daysToKeep
     * @return void
     */
    public function cleanupPerformanceStats(int $daysToKeep = 7): void
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);
            
            // 这里可以实现清理逻辑，删除过期的性能统计数据
            // 具体实现取决于缓存驱动和存储策略
            
            Log::info('Performance stats cleanup completed', [
                'cutoff_date' => $cutoffDate->toISOString(),
                'days_kept' => $daysToKeep
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup performance stats', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 启用性能监控
     *
     * @return void
     */
    public function enablePerformanceMonitoring(): void
    {
        $this->enablePerformanceMonitoring = true;
    }

    /**
     * 禁用性能监控
     *
     * @return void
     */
    public function disablePerformanceMonitoring(): void
    {
        $this->enablePerformanceMonitoring = false;
    }

    /**
     * 监控操作性能
     *
     * @param string $operation
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    protected function monitorPerformance(string $operation, callable $callback)
    {
        if (!$this->enablePerformanceMonitoring) {
            return $callback();
        }

        $this->startPerformanceMonitoring($operation);
        
        try {
            $result = $callback();
            $this->endPerformanceMonitoring($operation);
            return $result;
        } catch (\Exception $e) {
            $this->endPerformanceMonitoring($operation, ['error' => $e->getMessage()]);
            
            Log::error('Cart operation failed', [
                'operation' => $operation,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * 格式化内存使用量
     *
     * @param int $bytes
     * @return string
     */
    protected function formatMemoryUsage(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        if ($unitIndex === 0) {
            return $size . $units[$unitIndex];
        }

        return number_format($size, 2) . $units[$unitIndex];
    }

    /**
     * 格式化执行时间
     *
     * @param float $microseconds
     * @return string
     */
    protected function formatExecutionTime(float $microseconds): string
    {
        if ($microseconds < 1000) {
            // For microseconds, don't show decimals if it's a whole number
            return ($microseconds == (int)$microseconds) ? (int)$microseconds . 'μs' : number_format($microseconds, 2) . 'μs';
        }

        $milliseconds = $microseconds / 1000;
        if ($milliseconds < 1000) {
            return number_format($milliseconds, 2) . 'ms';
        }

        $seconds = $milliseconds / 1000;
        return number_format($seconds, 2) . 's';
    }

    /**
     * 生成性能缓存键
     *
     * @param string $operation
     * @return string
     */
    protected function getPerformanceCacheKey(string $operation): string
    {
        return config('shopping-cart.performance.cache_key', 'cart_performance_stats') . ':' . $operation;
    }
}