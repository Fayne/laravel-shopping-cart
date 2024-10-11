<?php

namespace MobileNowGroup\ShoppingCart\Http\Controllers;

use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowBefore;
use MobileNowGroup\ShoppingCart\Http\Requests\Checkout\BackFromOnePageRequest;
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
        event(new ShowBefore);

        $cart = ShoppingCart::getCart(true);

        event(new ShowAfter($cart));

        return response()->json([
            'code' => 0,
            'data' => $cart && !$cart->is_temp ? new CartResource($cart) : null,
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
        event(new OnePageBefore);

        $cart = ShoppingCart::getCart();

        event(new OnePageAfter($cart));

        return response()->json([
            'code' => 0,
            'data' => $cart && $cart->is_temp ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Get customer cart for buy now .
     *
     * @param BackFromOnePageRequest $request
     * @return JsonResponse
     */
    public function backFromOnePage(BackFromOnePageRequest $request): JsonResponse
    {
        event(new BackFromOnePageBefore);

        ShoppingCart::deActivateCart($request->cart);
        ShoppingCart::activateCartIfCacheHasDeactivatedCartId();

        $cart = ShoppingCart::getCart();

        event(new BackFromOnePageAfter($cart));

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
