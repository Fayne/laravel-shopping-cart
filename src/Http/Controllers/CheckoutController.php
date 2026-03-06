<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use MobileNowGroup\LaravelNovaSku\Models\ProductSku;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\Checkout;
use MobileNowGroup\ShoppingCart\Models\CheckoutItem;

class CheckoutController extends Controller
{
    /**
     * 创建订单（结账）
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function __invoke(Request $request): JsonResponse
    {
        $cart = ShoppingCart::getCart();
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        // 获取选中的购物车项
        $selectedItems = $cart->items->where('selected', true);
        if ($selectedItems->isEmpty()) {
            return response()->json(['message' => 'No items selected for checkout'], 400);
        }

        try {
            return DB::transaction(function () use ($cart, $selectedItems) {
                // 1. 创建 Checkout 记录
                $checkout = Checkout::create([
                    'user_id' => auth()->id(),
                    'status' => 0, // Pending
                    'currency' => 'CNY', // 这里可以根据配置或请求动态设置
                    'subtotal_amount' => $selectedItems->sum('subtotal'),
                    'discount_amount' => $cart->discount_amount ?? 0, // 需要根据实际逻辑计算分摊后的折扣
                    'tax_amount' => 0, // 暂不计算税费
                    'shipping_amount' => 0, // 暂不计算运费
                    'total_amount' => $selectedItems->sum('total'),
                    'metadata' => $cart->metadata,
                    'expires_at' => now()->addMinutes(30), // 订单有效期
                ]);

                // 2. 创建 Checkout Items
                foreach ($selectedItems as $item) {
                    $sku = ProductSku::find($item->itemable_id);

                    CheckoutItem::create([
                        'checkout_id' => $checkout->id,
                        'product_id' => $sku->product_id,
                        'sku_id' => $item->itemable_id,
                        'name_snapshot' => $item->name,
                        'price_snapshot' => $item->price,
                        'quantity' => $item->quantity,
                    ]);
                }

                // 3. 从购物车中移除已购买的商品
                // 注意：这里我们只移除选中的商品，保留未选中的
                foreach ($selectedItems as $item) {
                    $item->delete();
                }
                
                // 重新计算购物车总额（如果还有剩余商品）
                if ($cart->items()->count() > 0) {
                    ShoppingCart::collectTotals();
                } else {
                    // 如果购物车空了，可以考虑清理购物车记录或重置状态
                    $cart->delete();
                }

                // 4. 返回结果
                return response()->json([
                    'code' => 0,
                    'message' => '订单创建成功',
                    'data' => [
                        'checkout_id' => $checkout->id,
                        'total_amount' => $checkout->total_amount,
                        'expires_at' => $checkout->expires_at,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Checkout failed: ' . $e->getTraceAsString());
            return response()->json([
                'code' => 500,
                'message' => '订单创建失败，请稍后重试',
            ], 500);
        }
    }
}
