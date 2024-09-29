<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use MobileNowGroup\ShoppingCart\Events\ShopCart\CartEmptyAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartEmptyBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemRemoveAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemRemoveBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartRemoveCouponAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartRemoveCouponBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartShowAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartShowBefore;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\ApplyCouponRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\EmptyCartRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\RemoveRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\StoreRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\UpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class CartController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): ?JsonResponse
    {
        return $request->handle();
    }

    /**
     * Get customer cart.
     *
     * @return JsonResponse
     */
    public function show()
    {
        event(new CartShowBefore);

        $cart = ShoppingCart::getCart();

        event(new CartShowAfter($cart));

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request)
    {
        return $request->handle();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param EmptyCartRequest $request
     * @return JsonResponse
     */
    public function destroy(EmptyCartRequest $request): JsonResponse
    {
        event(new CartEmptyBefore);

        $cart = ShoppingCart::emptyCart();

        event(new CartEmptyAfter($cart));

        return response()->json([
            'code' => 0,
            'message' => '删除购物车商品成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param RemoveRequest $request
     * @return JsonResponse
     */
    public function removeItem(RemoveRequest $request): JsonResponse
    {
        event(new CartItemRemoveBefore($request->validated('cart_item_id')));

        ShoppingCart::removeItem($request->validated('cart_item_id'));

        $cart = ShoppingCart::getCart();

        event(new CartItemRemoveAfter($request->validated('cart_item_id'), $cart));

        return response()->json([
            'code' => 0,
            'message' => '移除商品成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Apply coupon code.
     *
     * @param ApplyCouponRequest $request
     * @return JsonResponse
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        return $request->handle();
    }

    /**
     * Remove coupon code.
     *
     * @return JsonResponse
     */
    public function removeCoupon()
    {
        event(new CartRemoveCouponBefore);

        ShoppingCart::removeCouponCode()->collectTotals();

        event(new CartRemoveCouponAfter);

        return response()->json([
            'code' => 0,
            'message' => '移除优惠券成功',
        ]);
    }
}
