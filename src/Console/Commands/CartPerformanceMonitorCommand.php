<?php

namespace Fayne\ShoppingCart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Fayne\ShoppingCart\Cache\CartCacheOptimizer;
use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Storage\CartStorageManager;

/**
 * 购物车性能监控命令
 * 用于监控和分析购物车系统的性能指标
 */
class CartPerformanceMonitorCommand extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'cart:monitor 
                            {--action= : Action to perform (report, analyze, cleanup, benchmark)}
                            {--period=24h : Time period for analysis (1h, 24h, 7d, 30d)}
                            {--format=table : Output format (table, json, csv)}
                            {--export= : Export results to file}
                            {--threshold-response=1000 : Response time threshold in ms}
                            {--threshold-memory=50 : Memory usage threshold in MB}
                            {--benchmark-iterations=100 : Number of iterations for benchmark}';

    /**
     * 命令描述
     */
    protected $description = 'Monitor and analyze shopping cart performance metrics';

    /**
     * 缓存优化器
     */
    protected CartCacheOptimizer $cacheOptimizer;

    /**
     * 存储管理器
     */
    protected CartStorageManager $storageManager;

    /**
     * 购物车模型
     */
    protected $cartModel;

    /**
     * 构造函数
     */
    public function __construct(CartCacheOptimizer $cacheOptimizer, CartStorageManager $storageManager, $cartModel = null)
    {
        parent::__construct();
        $this->cacheOptimizer = $cacheOptimizer;
        $this->storageManager = $storageManager;
        $this->cartModel = $cartModel ?: new ShoppingCart;
    }

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $action = $this->option('action');

        if (!$action) {
            $this->error('Please specify a valid action: report, analyze, cleanup, or benchmark');

            return self::FAILURE;
        }

        try {
            switch ($action) {
                case 'report':
                    return $this->generatePerformanceReport();
                case 'analyze':
                    return $this->analyzePerformance();
                case 'cleanup':
                    return $this->cleanupMonitoringData();
                case 'benchmark':
                    return $this->runBenchmark();
                default:
                    $this->error("Unknown action: {$action}");

                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Cart performance monitor command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * 生成性能报告
     */
    protected function generatePerformanceReport(): int
    {
        $period = $this->option('period');
        $format = $this->option('format');

        // 验证 period 参数
        if (!$this->validatePeriod($period)) {
            return self::FAILURE;
        }

        // 收集性能数据
        $data = $this->collectPerformanceData($period);

        // 输出报告
        $this->outputReport($data, $format, $period);

        // 导出文件
        if ($exportFile = $this->option('export')) {
            $this->exportReport($data, $exportFile, $format);
        }

        return self::SUCCESS;
    }

    /**
     * 分析性能问题
     */
    protected function analyzePerformance(): int
    {
        $this->info('Performance Analysis');
        $this->info('===================');

        $responseThreshold = (int) $this->option('threshold-response');
        $memoryThreshold = (int) $this->option('threshold-memory');

        // 获取操作数据
        $operations = Cache::get('cart_performance_operations', []);
        $issues = $this->identifyPerformanceIssues($responseThreshold, $memoryThreshold, $operations);

        if (empty($issues)) {
            $this->info('✅ No performance issues detected!');
            $this->info('Your cart system is performing well.');
        } else {
            $this->info('Issues Found:');
            foreach ($issues as $issue) {
                $this->line("⚠️  {$issue['description']}");
            }
            $this->info('Recommendations:');
            foreach ($issues as $issue) {
                if (isset($issue['recommendation'])) {
                    $this->line("- {$issue['recommendation']}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * 清理监控数据
     */
    protected function cleanupMonitoringData(): int
    {
        $this->info('Cleaning up old monitoring data...');

        $removedCount = $this->cacheOptimizer->cleanupMonitoringData();

        if ($removedCount) {
            $this->info('Cleanup completed successfully!');
        } else {
            $this->error('❌ Failed to cleanup monitoring data.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 运行性能基准测试
     */
    protected function runBenchmark(): int
    {
        $this->info('Running Cart Performance Benchmark');
        $this->info('==================================');

        $this->info('Testing cart operations...');

        $iterations = (int) $this->option('benchmark-iterations');

        try {
            $results = $this->performBenchmark($iterations);

            $this->displayBenchmarkResults($results);

            $this->info('Benchmark completed!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during benchmark:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * 收集性能数据
     */
    protected function collectPerformanceData(string $period): array
    {
        $data = [
            'cache_stats' => $this->cacheOptimizer->getCacheStats(),
            'database_stats' => $this->getDatabaseStats($period),
            'cart_stats' => $this->getCartStats($period),
            'system_stats' => $this->getSystemStats(),
            'operation_breakdown' => $this->getOperationBreakdown($period),
            'period' => $period,
            'generated_at' => now()->toISOString(),
        ];

        return $data;
    }

    /**
     * 获取数据库统计信息
     */
    protected function getDatabaseStats(string $period): array
    {
        $hours = $this->parsePeriodToHours($period);
        $since = now()->subHours($hours);

        return [
            'total_carts' => $this->cartModel->count(),
            'active_carts' => $this->cartModel->where('is_active', true)->count(),
            'recent_carts' => $this->cartModel->where('updated_at', '>=', $since)->count(),
            'avg_items_per_cart' => $this->cartModel->avg('items_count') ?? 0,
            'avg_cart_value' => $this->cartModel->avg('grand_total') ?? 0,
        ];
    }

    /**
     * 验证 period 参数
     */
    protected function validatePeriod(string $period): bool
    {
        // 解析天数
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            $days = (int) $matches[1];

            if ($days <= 0) {
                $this->error('Error: Days must be a positive integer.');

                return false;
            }

            if ($days > 90) {
                $this->error('Error: Days cannot exceed 90.');

                return false;
            }
        }

        return true;
    }

    /**
     * 获取操作分解统计
     */
    protected function getOperationBreakdown(string $period): array
    {
        $operations = Cache::get('cart_performance_operations', []);
        $breakdown = [];

        foreach ($operations as $operation) {
            $opName = $operation['operation'];
            if (!isset($breakdown[$opName])) {
                $breakdown[$opName] = [
                    'times' => [],
                    'count' => 0,
                ];
            }
            $breakdown[$opName]['times'][] = $operation['execution_time'];
            $breakdown[$opName]['count']++;
        }

        $result = [];
        foreach ($breakdown as $opName => $data) {
            $avgTime = array_sum($data['times']) / count($data['times']);
            $result[$opName] = [
                'count' => $data['count'],
                'avg_time' => number_format($avgTime, 2),
            ];
        }

        return $result;
    }

    /**
     * 获取购物车统计信息
     */
    protected function getCartStats(string $period): array
    {
        $hours = $this->parsePeriodToHours($period);
        $since = now()->subHours($hours);

        return [
            'operations_count' => $this->getOperationsCount($since),
            'avg_response_time' => $this->getAverageResponseTime($since),
            'error_rate' => $this->getErrorRate($since),
            'peak_memory_usage' => $this->getPeakMemoryUsage($since),
        ];
    }

    /**
     * 获取系统统计信息
     */
    protected function getSystemStats(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'current_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'cache_driver' => config('cache.default'),
            'database_driver' => config('database.default'),
        ];
    }

    /**
     * 输出报告
     */
    protected function outputReport(array $data, string $format, string $period = '24h'): void
    {
        // 获取操作数据一次并重用
        $operations = Cache::get('cart_performance_operations', []);

        // 检查是否有性能数据
        $hasCacheStats = isset($data['cache_stats']) && !empty($data['cache_stats']);
        $hasOperationData = isset($data['operation_breakdown']) && !empty($data['operation_breakdown']);
        $hasOperations = !empty($operations);

        if (!$hasCacheStats && !$hasOperationData && !$hasOperations) {
            $this->info('Cart Performance Report');
            $this->info('No performance data available.');

            return;
        }

        switch ($format) {
            case 'json':
                $this->outputJsonReport($data, $period);
                break;
            case 'csv':
                $this->outputCsvReport($data, $period);
                break;
            default:
                $this->outputTableReport($data, $operations, $period);
                break;
        }
    }

    /**
     * 输出表格报告
     */
    protected function outputTableReport(array $data, array $operations = [], string $period = '24h'): void
    {
        // 显示标题
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            $days = (int) $matches[1];
            $this->info("Cart Performance Report (Last {$days} days)");
        } else {
            $this->info('Cart Performance Report');
        }

        // 缓存统计
        $this->info('Cache Statistics:');
        $this->line('- Cache Hits: ' . ($data['cache_stats']['cache_hits'] ?? 0));
        $this->line('- Cache Misses: ' . ($data['cache_stats']['cache_misses'] ?? 0));
        $this->line('- Hit Rate: ' . number_format($data['cache_stats']['hit_rate'] ?? 0, 2) . '%');

        // 操作性能统计
        $this->info('Operation Performance:');

        // 使用传入的操作数据
        if (!empty($operations)) {
            $totalOperations = count($operations);
            $this->line('- Total Operations: ' . $totalOperations);

            // 计算平均响应时间
            $totalTime = array_sum(array_column($operations, 'execution_time'));
            $avgResponseTime = number_format($totalTime / $totalOperations, 2);
            $this->line("- Average Response Time: {$avgResponseTime}ms");

            // 计算慢操作数量
            $slowOperations = count(array_filter($operations, function ($op) {
                return $op['execution_time'] > 100;
            }));
            $this->line("- Slow Operations (>100ms): {$slowOperations}");
        } elseif (isset($data['operation_breakdown']) && !empty($data['operation_breakdown'])) {
            $totalOperations = array_sum(array_column($data['operation_breakdown'], 'count'));
            $this->line('- Total Operations: ' . $totalOperations);

            // 计算平均响应时间
            $totalTime = 0;
            $totalCount = 0;
            $slowOperations = 0;

            foreach ($data['operation_breakdown'] as $operation => $stats) {
                $totalTime += $stats['avg_time'] * $stats['count'];
                $totalCount += $stats['count'];
                if ($stats['avg_time'] > 100) {
                    $slowOperations += $stats['count'];
                }
            }

            if ($totalCount > 0) {
                $avgResponseTime = number_format($totalTime / $totalCount, 2);
                $this->line("- Average Response Time: {$avgResponseTime}ms");
            }

            $this->line("- Slow Operations (>100ms): {$slowOperations}");
        }

        // 内存使用统计
        $this->info('Memory Usage:');
        $currentMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("- Current Memory: {$currentMemory}MB");
        $this->line("- Peak Memory: {$peakMemory}MB");

        // 操作分解
        if (isset($data['operation_breakdown']) && !empty($data['operation_breakdown'])) {
            $this->info('Operation Breakdown:');
            foreach ($data['operation_breakdown'] as $operation => $stats) {
                $this->line("- {$operation}: {$stats['count']} operations, avg {$stats['avg_time']}ms");
            }
        }
    }

    /**
     * 识别性能问题
     */
    protected function identifyPerformanceIssues(int $responseThreshold, int $memoryThreshold, array $operations = []): array
    {
        $issues = [];

        // 检查缓存命中率
        $cacheStats = $this->cacheOptimizer->getCacheStats();
        $hitRate = $cacheStats['hit_rate'] ?? 0;
        if ($hitRate < 80) {
            $issues[] = [
                'type' => 'Low Cache Hit Rate',
                'description' => 'Low cache hit rate: ' . number_format($hitRate, 2) . '% (recommended: >80%)',
                'recommendation' => 'Increase cache TTL or optimize cache strategy',
            ];
        }

        // 检查平均响应时间
        $avgResponseTime = $cacheStats['avg_response_time'] ?? 0;
        if ($avgResponseTime > 100) {
            $issues[] = [
                'type' => 'High Response Time',
                'description' => 'High average response time: ' . number_format($avgResponseTime, 2) . 'ms (recommended: <100ms)',
                'recommendation' => 'Review and optimize slow database queries',
            ];
        }

        // 检查慢操作数量
        $slowOperations = $cacheStats['slow_operations'] ?? 0;
        if ($slowOperations > 5) {
            $issues[] = [
                'type' => 'High Slow Operations',
                'description' => "High number of slow operations: {$slowOperations} (recommended: <5)",
                'recommendation' => 'Consider implementing query result caching',
            ];
        }

        // 检查内存使用
        // 使用传入的operations参数
        if (!empty($operations)) {
            $maxMemory = max(array_column($operations, 'memory_usage'));
            if ($maxMemory > 128) {
                $issues[] = [
                    'type' => 'High Memory Usage',
                    'description' => "High memory usage detected: {$maxMemory}MB (recommended: <128MB)",
                    'recommendation' => 'Optimize memory usage in cart operations',
                ];
            }
        }

        // 检查数据库连接
        try {
            $connectionTime = $this->measureDatabaseConnectionTime();
            if ($connectionTime > 100) { // 100ms threshold
                $issues[] = [
                    'type' => 'Slow Database Connection',
                    'description' => "Database connection time is {$connectionTime}ms (> 100ms)",
                    'recommendation' => 'Check database server performance or network latency',
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'type' => 'Database Connection Error',
                'description' => 'Failed to connect to database: ' . $e->getMessage(),
                'recommendation' => 'Check database configuration and connectivity',
            ];
        }

        return $issues;
    }

    /**
     * 执行基准测试
     */
    protected function performBenchmark(int $iterations): array
    {
        try {
            $storage = $this->storageManager->driver();
        } catch (\Exception $e) {
            throw new \Exception('Failed to initialize storage driver: ' . $e->getMessage());
        }

        $results = [
            'create_cart' => [],
            'get_cart' => [],
            'add_item' => [],
            'update_item' => [],
            'remove_item' => [],
        ];

        $this->info("Running {$iterations} iterations for each operation...");
        $progressBar = $this->output->createProgressBar($iterations * 5);

        for ($i = 0; $i < $iterations; $i++) {
            $customerId = 'benchmark_' . $i;

            try {
                // 测试创建购物车
                $startTime = microtime(true);
                $cart = $storage->createCart($customerId);
                $results['create_cart'][] = microtime(true) - $startTime;
                $progressBar->advance();

                // 测试获取购物车
                $startTime = microtime(true);
                $storage->getCart($customerId);
                $results['get_cart'][] = microtime(true) - $startTime;
                $progressBar->advance();

                // 测试添加商品
                $startTime = microtime(true);
                $storage->addItem($cart->id, [
                    'product_id' => 1,
                    'quantity' => 1,
                    'price' => 10.00,
                    'name' => 'Test Product',
                ]);
                $results['add_item'][] = microtime(true) - $startTime;
                $progressBar->advance();

                // 测试更新商品
                $startTime = microtime(true);
                $storage->updateItems($cart->id, [['quantity' => 2]]);
                $results['update_item'][] = microtime(true) - $startTime;
                $progressBar->advance();

                // 测试删除商品
                $startTime = microtime(true);
                $storage->removeItem($cart->id, 1);
                $results['remove_item'][] = microtime(true) - $startTime;
                $progressBar->advance();
            } catch (\Exception $e) {
                // 如果单个操作失败，记录到日志但继续测试
                Log::warning('Benchmark operation failed', [
                    'iteration' => $i,
                    'error' => $e->getMessage(),
                ]);
                // 为失败的操作添加默认时间
                $results['create_cart'][] = 0;
                $results['get_cart'][] = 0;
                $results['add_item'][] = 0;
                $results['update_item'][] = 0;
                $results['remove_item'][] = 0;
                $progressBar->advance(5);
            }
        }

        $progressBar->finish();
        $this->newLine();

        // 计算统计信息
        foreach ($results as $operation => &$times) {
            if (count($times) > 0) {
                $times = [
                    'avg' => array_sum($times) / count($times),
                    'min' => min($times),
                    'max' => max($times),
                    'median' => $this->calculateMedian($times),
                ];
            } else {
                $times = [
                    'avg' => 0,
                    'min' => 0,
                    'max' => 0,
                    'median' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * 显示基准测试结果
     */
    protected function displayBenchmarkResults(array $results): void
    {
        $this->info('Benchmark Results:');

        foreach ($results as $operation => $stats) {
            $operationName = ucfirst(str_replace('_', ' ', $operation));
            $avgTime = number_format($stats['avg'] * 1000, 2); // 转换为毫秒
            $this->line("- {$operationName}: {$avgTime}ms avg");
        }
    }

    /**
     * 输出JSON格式报告
     */
    protected function outputJsonReport(array $data, string $period = '24h'): void
    {
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            $days = (int) $matches[1];
            $this->info("Cart Performance Report (Last {$days} days)");
        } else {
            $this->info('Cart Performance Report');
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * 输出CSV格式报告
     */
    protected function outputCsvReport(array $data, string $period = '24h'): void
    {
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            $days = (int) $matches[1];
            $this->info("Cart Performance Report (Last {$days} days)");
        } else {
            $this->info('Cart Performance Report');
        }
        $this->line($this->convertToCsv($data));
    }

    /**
     * 解析时间周期为小时数
     */
    protected function parsePeriodToHours(string $period): int
    {
        $matches = [];
        if (preg_match('/^(\d+)([hdw])$/', $period, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 'h': return $value;
                case 'd': return $value * 24;
                case 'w': return $value * 24 * 7;
            }
        }

        return 24; // 默认24小时
    }

    /**
     * 获取操作数量
     */
    protected function getOperationsCount($since): int
    {
        $operations = Cache::get('cart_performance_operations', []);

        return count(array_filter($operations, function ($op) use ($since) {
            return isset($op['timestamp']) && $op['timestamp'] >= $since->timestamp;
        }));
    }

    /**
     * 获取平均响应时间
     */
    protected function getAverageResponseTime($since): float
    {
        $operations = Cache::get('cart_performance_operations', []);
        $recentOperations = array_filter($operations, function ($op) use ($since) {
            return isset($op['timestamp']) && $op['timestamp'] >= $since->timestamp;
        });

        if (empty($recentOperations)) {
            return 0.0;
        }

        $totalTime = array_sum(array_column($recentOperations, 'execution_time'));

        return $totalTime / count($recentOperations) / 1000; // 转换为秒
    }

    /**
     * 获取错误率
     */
    protected function getErrorRate($since): float
    {
        $operations = Cache::get('cart_performance_operations', []);
        $recentOperations = array_filter($operations, function ($op) use ($since) {
            return isset($op['timestamp']) && $op['timestamp'] >= $since->timestamp;
        });

        if (empty($recentOperations)) {
            return 0.0;
        }

        $errorCount = count(array_filter($recentOperations, function ($op) {
            return isset($op['status']) && $op['status'] === 'error';
        }));

        return $errorCount / count($recentOperations);
    }

    /**
     * 获取峰值内存使用
     */
    protected function getPeakMemoryUsage($since): string
    {
        return round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
    }

    /**
     * 测量数据库连接时间
     */
    protected function measureDatabaseConnectionTime(): float
    {
        $startTime = microtime(true);
        DB::connection()->getPdo();

        return (microtime(true) - $startTime) * 1000; // 转换为毫秒
    }

    /**
     * 计算中位数
     */
    protected function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }

    /**
     * 导出报告
     */
    protected function exportReport(array $data, string $filename, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->convertToCsv($data),
            default => serialize($data)
        };

        file_put_contents($filename, $content);
        $this->info("Report exported to: {$filename}");
    }

    /**
     * 转换为CSV格式
     */
    protected function convertToCsv(array $data): string
    {
        $csv = "Metric,Value\n";

        foreach ($data as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $csv .= "{$section}.{$key}," . (is_numeric($value) ? $value : '"' . $value . '"') . "\n";
                }
            } else {
                $csv .= "{$section}," . (is_numeric($values) ? $values : '"' . $values . '"') . "\n";
            }
        }

        return $csv;
    }
}
