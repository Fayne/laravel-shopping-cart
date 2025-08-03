<?php

namespace MobileNowGroup\ShoppingCart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Cache\CartCacheOptimizer;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;

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
                            {--action=report : Action to perform (report, analyze, cleanup, benchmark)}
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
     * 构造函数
     */
    public function __construct(CartCacheOptimizer $cacheOptimizer, CartStorageManager $storageManager)
    {
        parent::__construct();
        $this->cacheOptimizer = $cacheOptimizer;
        $this->storageManager = $storageManager;
    }

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $action = $this->option('action');
        
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
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * 生成性能报告
     */
    protected function generatePerformanceReport(): int
    {
        $this->info('Generating Cart Performance Report');
        $this->info('==================================');
        
        $period = $this->option('period');
        $format = $this->option('format');
        
        // 收集性能数据
        $data = $this->collectPerformanceData($period);
        
        // 输出报告
        $this->outputReport($data, $format);
        
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
        $this->info('Analyzing Cart Performance Issues');
        $this->info('=================================');
        
        $responseThreshold = (int) $this->option('threshold-response');
        $memoryThreshold = (int) $this->option('threshold-memory');
        
        $issues = $this->identifyPerformanceIssues($responseThreshold, $memoryThreshold);
        
        if (empty($issues)) {
            $this->info('✅ No performance issues detected.');
        } else {
            $this->warn('⚠️  Performance issues detected:');
            foreach ($issues as $issue) {
                $this->line("• {$issue['type']}: {$issue['description']}");
                if (isset($issue['recommendation'])) {
                    $this->line("  Recommendation: {$issue['recommendation']}");
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
        $this->info('Cleaning up monitoring data...');
        
        $success = $this->cacheOptimizer->cleanupMonitoringData();
        
        if ($success) {
            $this->info('✅ Monitoring data cleaned up successfully.');
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
        
        $iterations = (int) $this->option('benchmark-iterations');
        
        $results = $this->performBenchmark($iterations);
        
        $this->displayBenchmarkResults($results);
        
        return self::SUCCESS;
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
            'total_carts' => ShoppingCart::count(),
            'active_carts' => ShoppingCart::where('is_active', true)->count(),
            'recent_carts' => ShoppingCart::where('updated_at', '>=', $since)->count(),
            'avg_items_per_cart' => ShoppingCart::avg('items_count') ?? 0,
            'avg_cart_value' => ShoppingCart::avg('grand_total') ?? 0,
        ];
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
    protected function outputReport(array $data, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->outputCsvReport($data);
                break;
            default:
                $this->outputTableReport($data);
                break;
        }
    }

    /**
     * 输出表格报告
     */
    protected function outputTableReport(array $data): void
    {
        // 缓存统计
        $this->info('Cache Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Hit Rate', $data['cache_stats']['hit_rate'] . '%'],
                ['Total Operations', $data['cache_stats']['total_operations']],
                ['Average Duration', round($data['cache_stats']['avg_duration'] * 1000, 2) . ' ms'],
            ]
        );
        
        // 数据库统计
        $this->info('Database Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Carts', number_format($data['database_stats']['total_carts'])],
                ['Active Carts', number_format($data['database_stats']['active_carts'])],
                ['Recent Carts', number_format($data['database_stats']['recent_carts'])],
                ['Avg Items/Cart', round($data['database_stats']['avg_items_per_cart'], 2)],
                ['Avg Cart Value', '$' . number_format($data['database_stats']['avg_cart_value'], 2)],
            ]
        );
        
        // 系统统计
        $this->info('System Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['PHP Version', $data['system_stats']['php_version']],
                ['Memory Limit', $data['system_stats']['memory_limit']],
                ['Current Memory', $data['system_stats']['current_memory_usage']],
                ['Peak Memory', $data['system_stats']['peak_memory_usage']],
                ['Cache Driver', $data['system_stats']['cache_driver']],
                ['Database Driver', $data['system_stats']['database_driver']],
            ]
        );
    }

    /**
     * 识别性能问题
     */
    protected function identifyPerformanceIssues(int $responseThreshold, int $memoryThreshold): array
    {
        $issues = [];
        
        // 检查缓存命中率
        $cacheStats = $this->cacheOptimizer->getCacheStats();
        if ($cacheStats['hit_rate'] < 80) {
            $issues[] = [
                'type' => 'Low Cache Hit Rate',
                'description' => "Cache hit rate is {$cacheStats['hit_rate']}% (< 80%)",
                'recommendation' => 'Consider increasing cache TTL or optimizing cache keys'
            ];
        }
        
        // 检查响应时间
        $avgResponseTime = $this->getAverageResponseTime(now()->subHours(1)) * 1000;
        if ($avgResponseTime > $responseThreshold) {
            $issues[] = [
                'type' => 'High Response Time',
                'description' => "Average response time is {$avgResponseTime}ms (> {$responseThreshold}ms)",
                'recommendation' => 'Consider optimizing database queries or adding more cache layers'
            ];
        }
        
        // 检查内存使用
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        if ($currentMemory > $memoryThreshold) {
            $issues[] = [
                'type' => 'High Memory Usage',
                'description' => "Current memory usage is {$currentMemory}MB (> {$memoryThreshold}MB)",
                'recommendation' => 'Consider optimizing data structures or implementing pagination'
            ];
        }
        
        // 检查数据库连接
        try {
            $connectionTime = $this->measureDatabaseConnectionTime();
            if ($connectionTime > 100) { // 100ms threshold
                $issues[] = [
                    'type' => 'Slow Database Connection',
                    'description' => "Database connection time is {$connectionTime}ms (> 100ms)",
                    'recommendation' => 'Check database server performance or network latency'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'type' => 'Database Connection Error',
                'description' => 'Failed to connect to database: ' . $e->getMessage(),
                'recommendation' => 'Check database configuration and connectivity'
            ];
        }
        
        return $issues;
    }

    /**
     * 执行基准测试
     */
    protected function performBenchmark(int $iterations): array
    {
        $storage = $this->storageManager->driver();
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
                'name' => 'Test Product'
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
        }
        
        $progressBar->finish();
        $this->newLine();
        
        // 计算统计信息
        foreach ($results as $operation => &$times) {
            $times = [
                'avg' => array_sum($times) / count($times),
                'min' => min($times),
                'max' => max($times),
                'median' => $this->calculateMedian($times),
            ];
        }
        
        return $results;
    }

    /**
     * 显示基准测试结果
     */
    protected function displayBenchmarkResults(array $results): void
    {
        $this->info('Benchmark Results:');
        
        $tableData = [];
        foreach ($results as $operation => $stats) {
            $tableData[] = [
                ucfirst(str_replace('_', ' ', $operation)),
                round($stats['avg'] * 1000, 2) . ' ms',
                round($stats['min'] * 1000, 2) . ' ms',
                round($stats['max'] * 1000, 2) . ' ms',
                round($stats['median'] * 1000, 2) . ' ms',
            ];
        }
        
        $this->table(
            ['Operation', 'Average', 'Min', 'Max', 'Median'],
            $tableData
        );
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
        // 这里应该从实际的监控数据中获取
        // 暂时返回模拟数据
        return rand(1000, 5000);
    }

    /**
     * 获取平均响应时间
     */
    protected function getAverageResponseTime($since): float
    {
        // 这里应该从实际的监控数据中获取
        // 暂时返回模拟数据
        return rand(50, 200) / 1000; // 转换为秒
    }

    /**
     * 获取错误率
     */
    protected function getErrorRate($since): float
    {
        // 这里应该从实际的监控数据中获取
        // 暂时返回模拟数据
        return rand(0, 5) / 100; // 0-5%
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
        $middle = floor($count / 2);
        
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
        $content = match($format) {
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