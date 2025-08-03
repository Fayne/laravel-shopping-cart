<?php

namespace MobileNowGroup\ShoppingCart\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartAddProductAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartRemoveItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartUpdateItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartEmptyAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartCollectTotalAfter;

/**
 * 购物车操作监听器
 * 处理购物车操作后的异步任务和通知
 */
class CartOperationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * 队列连接
     */
    public string $connection = 'default';

    /**
     * 队列名称
     */
    public string $queue = 'cart-operations';

    /**
     * 处理添加商品事件
     *
     * @param CartAddProductAfter $event
     * @return void
     */
    public function handleProductAdded(CartAddProductAfter $event): void
    {
        try {
            // 更新购物车统计
            $this->updateCartStatistics($event->cart->customer_id, 'product_added');
            
            // 记录用户行为
            $this->recordUserBehavior($event->cart->customer_id, 'add_to_cart', [
                'item_id' => $event->item->getKey(),
                'item_type' => get_class($event->item),
                'cart_id' => $event->cart->id,
            ]);
            
            // 触发推荐系统更新
            $this->updateRecommendations($event->cart->customer_id, $event->item);
            
            // 检查库存预警
            $this->checkInventoryAlert($event->item);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle product added event', [
                'cart_id' => $event->cart->id,
                'item_id' => $event->item->getKey(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理移除商品事件
     *
     * @param CartRemoveItemAfter $event
     * @return void
     */
    public function handleItemRemoved(CartRemoveItemAfter $event): void
    {
        try {
            // 记录移除行为
            $this->recordUserBehavior(auth()->id(), 'remove_from_cart', [
                'item_id' => $event->itemId,
            ]);
            
            // 更新购物车统计
            $this->updateCartStatistics(auth()->id(), 'item_removed');
            
        } catch (\Exception $e) {
            Log::error('Failed to handle item removed event', [
                'item_id' => $event->itemId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理更新商品事件
     *
     * @param CartUpdateItemAfter $event
     * @return void
     */
    public function handleItemUpdated(CartUpdateItemAfter $event): void
    {
        try {
            // 记录更新行为
            $this->recordUserBehavior($event->item->cart->customer_id, 'update_cart_item', [
                'item_id' => $event->item->id,
                'new_quantity' => $event->item->quantity,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle item updated event', [
                'item_id' => $event->item->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理清空购物车事件
     *
     * @param CartEmptyAfter $event
     * @return void
     */
    public function handleCartEmptied(CartEmptyAfter $event): void
    {
        try {
            if ($event->cart) {
                // 记录清空购物车行为
                $this->recordUserBehavior($event->cart->customer_id, 'empty_cart', [
                    'cart_id' => $event->cart->id,
                ]);
                
                // 更新购物车统计
                $this->updateCartStatistics($event->cart->customer_id, 'cart_emptied');
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to handle cart emptied event', [
                'cart_id' => $event->cart?->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理购物车总计计算事件
     *
     * @param CartCollectTotalAfter $event
     * @return void
     */
    public function handleTotalCalculated(CartCollectTotalAfter $event): void
    {
        try {
            // 缓存购物车总计信息
            $this->cacheCartTotals($event->cart);
            
            // 检查优惠券适用性
            $this->checkCouponEligibility($event->cart);
            
            // 更新用户购物偏好
            $this->updateShoppingPreferences($event->cart);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle total calculated event', [
                'cart_id' => $event->cart->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 更新购物车统计信息
     *
     * @param string $customerId
     * @param string $action
     * @return void
     */
    protected function updateCartStatistics(string $customerId, string $action): void
    {
        $cacheKey = "cart_stats:{$customerId}:" . date('Y-m-d');
        $stats = Cache::get($cacheKey, []);
        
        $stats[$action] = ($stats[$action] ?? 0) + 1;
        $stats['last_activity'] = now()->toISOString();
        
        Cache::put($cacheKey, $stats, now()->addDays(30));
    }

    /**
     * 记录用户行为
     *
     * @param string $customerId
     * @param string $action
     * @param array $data
     * @return void
     */
    protected function recordUserBehavior(string $customerId, string $action, array $data = []): void
    {
        // 这里可以集成用户行为分析系统
        Log::info('User cart behavior recorded', [
            'customer_id' => $customerId,
            'action' => $action,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * 更新推荐系统
     *
     * @param string $customerId
     * @param mixed $item
     * @return void
     */
    protected function updateRecommendations(string $customerId, $item): void
    {
        // 这里可以集成推荐系统API
        // 例如：更新用户偏好，触发相关商品推荐等
        
        Log::debug('Recommendation system update triggered', [
            'customer_id' => $customerId,
            'item_id' => $item->getKey(),
            'item_type' => get_class($item),
        ]);
    }

    /**
     * 检查库存预警
     *
     * @param mixed $item
     * @return void
     */
    protected function checkInventoryAlert($item): void
    {
        // 这里可以检查商品库存，如果库存低于阈值则发送预警
        // 具体实现取决于商品模型的库存字段
        
        if (method_exists($item, 'getStock')) {
            $stock = $item->getStock();
            $threshold = config('shopping-cart.inventory.low_stock_threshold', 10);
            
            if ($stock <= $threshold) {
                Log::warning('Low stock alert', [
                    'item_id' => $item->getKey(),
                    'current_stock' => $stock,
                    'threshold' => $threshold,
                ]);
                
                // 这里可以发送通知给管理员
            }
        }
    }

    /**
     * 缓存购物车总计信息
     *
     * @param mixed $cart
     * @return void
     */
    protected function cacheCartTotals($cart): void
    {
        $cacheKey = "cart_totals:{$cart->customer_id}";
        $totals = [
            'sub_total' => $cart->sub_total,
            'discount' => $cart->discount,
            'grand_total' => $cart->grand_total,
            'items_count' => $cart->items_count,
            'items_qty' => $cart->items_qty,
            'updated_at' => now()->toISOString(),
        ];
        
        Cache::put($cacheKey, $totals, now()->addHours(1));
    }

    /**
     * 检查优惠券适用性
     *
     * @param mixed $cart
     * @return void
     */
    protected function checkCouponEligibility($cart): void
    {
        // 这里可以检查当前购物车是否符合某些优惠券的使用条件
        // 如果符合条件，可以发送通知给用户
        
        Log::debug('Checking coupon eligibility', [
            'cart_id' => $cart->id,
            'customer_id' => $cart->customer_id,
            'grand_total' => $cart->grand_total,
        ]);
    }

    /**
     * 更新用户购物偏好
     *
     * @param mixed $cart
     * @return void
     */
    protected function updateShoppingPreferences($cart): void
    {
        // 这里可以分析用户的购物车内容，更新用户的购物偏好
        // 例如：喜欢的品牌、价格区间、商品类别等
        
        $preferences = [
            'avg_item_price' => $cart->items_count > 0 ? $cart->sub_total / $cart->items_count : 0,
            'total_items' => $cart->items_count,
            'cart_value' => $cart->grand_total,
            'updated_at' => now()->toISOString(),
        ];
        
        $cacheKey = "user_preferences:{$cart->customer_id}";
        Cache::put($cacheKey, $preferences, now()->addDays(30));
    }

    /**
     * 处理失败的任务
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Cart operation listener job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}