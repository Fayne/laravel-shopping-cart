<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use MobileNowGroup\ShoppingCart\Http\Requests\Cart\ApplyCouponRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\RemoveRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\StoreRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Cart\UpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
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
        $cart = ShoppingCart::getCart();

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
     * @param $id
     * @return JsonResponse
     */
    public function destroy(RemoveRequest $request): JsonResponse
    {
        Event::dispatch('checkout.cart.item.delete.before', $request->validated('cart_item_id'));

        ShoppingCart::removeItem($request->validated('id'));

        Event::dispatch('checkout.cart.item.delete.after', $request->validated('cart_item_id'));

        ShoppingCart::collectTotals();

        $cart = ShoppingCart::getCart();

        return response()->json([
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
        ShoppingCart::removeCouponCode()->collectTotals();

        return response()->json([
            'code' => 0,
            'message' => '移除优惠券成功',
        ]);
    }
}
