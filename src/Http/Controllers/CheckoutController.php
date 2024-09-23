<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowBefore;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\OnePageRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\ShowRequest;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\ToggleBuyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class CheckoutController extends Controller
{
    /**
     * Get customer cart.
     *
     * @param ToggleBuyRequest $request
     * @return JsonResponse
     */
    public function toggleBuy(ToggleBuyRequest $request): JsonResponse
    {

        return $request->handle();
    }

    /**
     * Get customer cart.
     *
     * @param ShowRequest $request
     * @return JsonResponse
     */
    public function show(ShowRequest $request): JsonResponse
    {
        ShowBefore::dispatch();

        $cart = $request->cart;

        ShowAfter::dispatch($cart);

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Get customer cart for buy now .
     *
     * @param OnePageRequest $request
     * @return JsonResponse
     */
    public function onePage(OnePageRequest $request): JsonResponse
    {
        OnePageBefore::dispatch();

        $cart = $request->cart;

        OnePageAfter::dispatch($cart);

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Get customer cart for buy now .
     *
     * @return JsonResponse
     */
    public function backFromOnePage(): JsonResponse
    {
        BackFromOnePageBefore::dispatch();

        ShoppingCart::deActivateCart();
        $cart = ShoppingCart::activateCartIfCacheHasDeactivatedCartId();

        BackFromOnePageAfter::dispatch($cart);

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
