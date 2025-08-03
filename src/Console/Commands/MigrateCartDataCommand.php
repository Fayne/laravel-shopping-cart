<?php

namespace MobileNowGroup\ShoppingCart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MobileNowGroup\ShoppingCart\Storage\CartStorageManager;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Cache\CartCacheOptimizer;

/**
 * 购物车数据迁移命令
 * 用于在不同存储策略之间迁移购物车数据
 */
class MigrateCartDataCommand extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'cart:migrate-data 
                            {--from= : Source storage driver (database, cache, redis)}
                            {--to= : Target storage driver (database, cache, redis)}
                            {--batch-size=100 : Number of carts to process in each batch}
                            {--dry-run : Run without making actual changes}
                            {--customer-id= : Migrate specific customer cart only}
                            {--force : Force migration without confirmation}';

    /**
     * 命令描述
     */
    protected $description = 'Migrate shopping cart data between different storage drivers';

    /**
     * 存储管理器
     */
    protected CartStorageManager $storageManager;

    /**
     * 缓存优化器
     */
    protected CartCacheOptimizer $cacheOptimizer;

    /**
     * 构造函数
     */
    public function __construct(CartStorageManager $storageManager, CartCacheOptimizer $cacheOptimizer)
    {
        parent::__construct();
        $this->storageManager = $storageManager;
        $this->cacheOptimizer = $cacheOptimizer;
    }

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $customerId = $this->option('customer-id');
        $force = $this->option('force');

        // 验证参数
        if (!$this->validateOptions($from, $to)) {
            return self::FAILURE;
        }

        // 显示迁移信息
        $this->displayMigrationInfo($from, $to, $batchSize, $dryRun, $customerId);

        // 确认迁移
        if (!$force && !$dryRun && !$this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled.');
            return self::SUCCESS;
        }

        try {
            $startTime = microtime(true);
            
            // 执行迁移
            $result = $this->performMigration($from, $to, $batchSize, $dryRun, $customerId);
            
            $duration = microtime(true) - $startTime;
            
            // 显示结果
            $this->displayMigrationResult($result, $duration, $dryRun);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            Log::error('Cart data migration failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return self::FAILURE;
        }
    }

    /**
     * 验证选项
     */
    protected function validateOptions(?string $from, ?string $to): bool
    {
        $validDrivers = ['database', 'cache', 'redis', 'layered'];
        
        if (!$from) {
            $this->error('Source storage driver (--from) is required.');
            return false;
        }
        
        if (!$to) {
            $this->error('Target storage driver (--to) is required.');
            return false;
        }
        
        if (!in_array($from, $validDrivers)) {
            $this->error("Invalid source driver: {$from}. Valid drivers: " . implode(', ', $validDrivers));
            return false;
        }
        
        if (!in_array($to, $validDrivers)) {
            $this->error("Invalid target driver: {$to}. Valid drivers: " . implode(', ', $validDrivers));
            return false;
        }
        
        if ($from === $to) {
            $this->error('Source and target drivers cannot be the same.');
            return false;
        }
        
        return true;
    }

    /**
     * 显示迁移信息
     */
    protected function displayMigrationInfo(string $from, string $to, int $batchSize, bool $dryRun, ?string $customerId): void
    {
        $this->info('Shopping Cart Data Migration');
        $this->info('============================');
        $this->line("From: {$from}");
        $this->line("To: {$to}");
        $this->line("Batch Size: {$batchSize}");
        $this->line("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        
        if ($customerId) {
            $this->line("Customer ID: {$customerId}");
        } else {
            $this->line('Scope: All customers');
        }
        
        $this->newLine();
    }

    /**
     * 执行迁移
     */
    protected function performMigration(string $from, string $to, int $batchSize, bool $dryRun, ?string $customerId): array
    {
        $sourceStorage = $this->storageManager->driver($from);
        $targetStorage = $this->storageManager->driver($to);
        
        $result = [
            'total_carts' => 0,
            'migrated_carts' => 0,
            'failed_carts' => 0,
            'total_items' => 0,
            'migrated_items' => 0,
            'failed_items' => 0,
            'errors' => []
        ];
        
        // 获取要迁移的购物车
        $carts = $this->getCartsToMigrate($customerId);
        $result['total_carts'] = $carts->count();
        
        if ($result['total_carts'] === 0) {
            $this->info('No carts found to migrate.');
            return $result;
        }
        
        $this->info("Found {$result['total_carts']} carts to migrate.");
        
        // 创建进度条
        $progressBar = $this->output->createProgressBar($result['total_carts']);
        $progressBar->start();
        
        // 分批处理
        $carts->chunk($batchSize, function ($cartBatch) use (
            $sourceStorage, $targetStorage, $dryRun, &$result, $progressBar
        ) {
            foreach ($cartBatch as $cart) {
                try {
                    $this->migrateCart($cart, $sourceStorage, $targetStorage, $dryRun, $result);
                    $result['migrated_carts']++;
                } catch (\Exception $e) {
                    $result['failed_carts']++;
                    $result['errors'][] = [
                        'cart_id' => $cart->id,
                        'customer_id' => $cart->customer_id,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::warning('Failed to migrate cart', [
                        'cart_id' => $cart->id,
                        'customer_id' => $cart->customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $progressBar->advance();
            }
        });
        
        $progressBar->finish();
        $this->newLine();
        
        return $result;
    }

    /**
     * 获取要迁移的购物车
     */
    protected function getCartsToMigrate(?string $customerId)
    {
        $query = ShoppingCart::with('items');
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        return $query->get();
    }

    /**
     * 迁移单个购物车
     */
    protected function migrateCart($cart, $sourceStorage, $targetStorage, bool $dryRun, array &$result): void
    {
        if ($dryRun) {
            // 干运行模式：只统计，不实际迁移
            $result['total_items'] += $cart->items->count();
            $result['migrated_items'] += $cart->items->count();
            return;
        }
        
        DB::transaction(function () use ($cart, $sourceStorage, $targetStorage, &$result) {
            // 迁移购物车基本信息
            $cartData = $cart->toArray();
            unset($cartData['id'], $cartData['created_at'], $cartData['updated_at']);
            
            $newCart = $targetStorage->createCart($cart->customer_id, $cartData);
            
            // 迁移购物车项目
            foreach ($cart->items as $item) {
                try {
                    $itemData = $item->toArray();
                    unset($itemData['id'], $itemData['cart_id'], $itemData['created_at'], $itemData['updated_at']);
                    
                    $targetStorage->addItem($newCart->id, $itemData);
                    $result['migrated_items']++;
                } catch (\Exception $e) {
                    $result['failed_items']++;
                    throw $e;
                }
            }
            
            $result['total_items'] += $cart->items->count();
            
            // 清除源存储的缓存（如果适用）
            if (method_exists($sourceStorage, 'clearCache')) {
                $sourceStorage->clearCache($cart->customer_id);
            }
            
            // 预热目标存储的缓存（如果适用）
            if (method_exists($targetStorage, 'warmupCache')) {
                $targetStorage->warmupCache($cart->customer_id);
            }
        });
    }

    /**
     * 显示迁移结果
     */
    protected function displayMigrationResult(array $result, float $duration, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Migration ' . ($dryRun ? 'Simulation ' : '') . 'Completed');
        $this->info('========================');
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Carts', $result['total_carts']],
                ['Migrated Carts', $result['migrated_carts']],
                ['Failed Carts', $result['failed_carts']],
                ['Total Items', $result['total_items']],
                ['Migrated Items', $result['migrated_items']],
                ['Failed Items', $result['failed_items']],
                ['Duration', round($duration, 2) . ' seconds'],
            ]
        );
        
        // 显示成功率
        $cartSuccessRate = $result['total_carts'] > 0 
            ? round(($result['migrated_carts'] / $result['total_carts']) * 100, 2) 
            : 0;
            
        $itemSuccessRate = $result['total_items'] > 0 
            ? round(($result['migrated_items'] / $result['total_items']) * 100, 2) 
            : 0;
        
        $this->info("Cart Success Rate: {$cartSuccessRate}%");
        $this->info("Item Success Rate: {$itemSuccessRate}%");
        
        // 显示错误信息
        if (!empty($result['errors'])) {
            $this->newLine();
            $this->error('Errors encountered during migration:');
            
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                $this->line("Cart {$error['cart_id']} (Customer {$error['customer_id']}): {$error['error']}");
            }
            
            if (count($result['errors']) > 10) {
                $remaining = count($result['errors']) - 10;
                $this->line("... and {$remaining} more errors. Check logs for details.");
            }
        }
        
        // 记录迁移日志
        Log::info('Cart data migration completed', [
            'result' => $result,
            'duration' => $duration,
            'dry_run' => $dryRun
        ]);
    }
}