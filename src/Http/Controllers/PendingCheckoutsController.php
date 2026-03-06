<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MobileNowGroup\ShoppingCart\Models\Checkout;

class PendingCheckoutsController extends Controller
{
    /**
     * 获取当前用户的未支付订单（Checkout 记录）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 获取该用户所有未支付且未过期的 Checkout 记录
        $checkouts = Checkout::query()->own()
            ->pending()
            ->active()
            ->with(['items']) // 预加载订单项
            ->latest()
            ->get();

        return response()->json([
            'code' => 0,
            'message' => '获取成功',
            'data' => $checkouts,
        ]);
    }
}
