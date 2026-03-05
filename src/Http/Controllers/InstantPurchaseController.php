<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageBefore;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\BackFromOnePageRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\OnePageRequest;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

/**
 * 立即购买控制器
 * 
 * 处理“立即购买”相关的逻辑，包括挂起当前购物车并创建临时购买会话，
 * 以及从临时会话恢复原来的购物车。
 */
class InstantPurchaseController extends Controller
{
    /**
     * 进入“立即购买”模式 (对应原 onePage)
     *
     * @param OnePageRequest $request
     * @return JsonResponse
     */
    public function store(OnePageRequest $request): JsonResponse
    {
        event(new OnePageBefore);

        $cart = $request->cart;

        event(new OnePageAfter($cart));

        return response()->json([
            'code' => 0,
            'message' => '已进入立即购买模式',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * 退出“立即购买”模式并恢复原购物车 (对应原 backFromOnePage)
     *
     * @param BackFromOnePageRequest $request
     * @return JsonResponse
     */
    public function destroy(BackFromOnePageRequest $request): JsonResponse
    {
        event(new BackFromOnePageBefore);

        ShoppingCart::deActivateCart();
        ShoppingCart::activateCartIfCacheHasDeactivatedCartId();

        $cart = ShoppingCart::getCart();

        event(new BackFromOnePageAfter($cart));

        return response()->json([
            'code' => 0,
            'message' => '已恢复购物车',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
