<?php

namespace Fayne\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Fayne\ShoppingCart\Events\ShopCart\CartApplyCouponAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartApplyCouponBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartEmptyAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartEmptyBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemAddAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemAddBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemIsBuyNow;
use Fayne\ShoppingCart\Events\ShopCart\CartItemRemoveAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemRemoveBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemUpdateAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemUpdateBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartRemoveCouponAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartRemoveCouponBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartShowAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartShowBefore;
use Fayne\ShoppingCart\Exceptions\ErrorConst;
use Fayne\ShoppingCart\Facades\ShoppingCart;
use Fayne\ShoppingCart\Http\Requests\Cart\ApplyCouponRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\EmptyCartRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\RemoveRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\SetCartItemSelectionRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\StoreRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\UpdateRequest;
use Fayne\ShoppingCart\Http\Resources\CartResource;
use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\CartServerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Illuminate\Database\Eloquent\Model;
use Fayne\ShoppingCart\Contracts\Cartable;

/**
 * 购物车控制器
 *
 * 处理购物车相关的请求，包括添加商品、更新数量、移除商品、清空购物车、查看购物车以及应用优惠券。
 */
class CartController extends Controller
{
    /**
     * 添加商品到购物车
     *
     * @param StoreRequest $request
     * @return JsonResponse
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function store(StoreRequest $request): JsonResponse
    {
        return ShoppingCart::transaction(function () use ($request) {
            $validated = $request->validated();
            $isBuyNow = $validated['is_buy_now'] ?? false;
            $code = $validated['code'];
            $quantity = $validated['quantity'];

            try {
                /** @var class-string<Model> $productSkuModel */
                $productSkuModel = config('shopping-cart.models.skus.model');
                $product = call_user_func([$productSkuModel, 'findByCodeOrFail'], $code);
                abort_unless($product instanceof Model && $product instanceof Cartable, 422, 'Invalid SKU model');
                $productSkuId = $product->getKey();

                // 如果是立即购买，先清理之前的立即购买商品
                if ($isBuyNow) {
                    ShoppingCart::deactivateCurrentCartIfBuyNowIsActive();
                    event(new CartItemIsBuyNow(
                        isBuyNow: $isBuyNow,
                        productSkuId: $productSkuId,
                        quantity: $quantity
                    ));
                }

                event(new CartItemAddBefore(
                    isBuyNow: $isBuyNow,
                    productSkuId: $productSkuId,
                    quantity: $quantity
                ));

                $cart = ShoppingCart::addProduct(
                    $product,
                    $quantity,
                    $isBuyNow
                );

                event(new CartItemAddAfter(
                    isBuyNow: $isBuyNow,
                    productSkuId: $productSkuId,
                    quantity: $quantity,
                    cart: $cart
                ));

                return response()->json([
                    'code' => 0,
                    'message' => '商品已成功添加到购物车。',
                    'data' => new CartResource($cart),
                ]);
            } catch (\Exception $e) {
                throw $e;
            }

        });
    }

    /**
     * 获取当前用户的购物车信息
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        event(new CartShowBefore);

        ShoppingCart::collectTotals();

        $cart = ShoppingCart::getCart();

        event(new CartShowAfter($cart));

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * 更新购物车中的商品数量
     *
     * @param UpdateRequest $request
     * @return JsonResponse
     *
     * @throws CartClientException
     */
    public function update(UpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $itemId = $validated['item_id'];
        $quantity = $validated['quantity'];

        event(new CartItemUpdateBefore(
            itemId: $itemId,
            quantity: $quantity
        ));

        $cart = ShoppingCart::updateItems($itemId, $quantity);

        // ShoppingCart::collectTotals();

        event(new CartItemUpdateAfter(
            itemId: $itemId,
            quantity: $quantity,
            cart: $cart
        ));

        return response()->json([
            'code' => 0,
            'message' => '购物车更新成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * 清空购物车
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
     * 从购物车中移除指定商品
     *
     * @param RemoveRequest $request
     * @return JsonResponse
     */
    public function removeItem(RemoveRequest $request): JsonResponse
    {
        $itemId = $request->validated('item_id');

        event(new CartItemRemoveBefore($itemId));

        ShoppingCart::removeItem($itemId);

        $cart = ShoppingCart::getCart();

        event(new CartItemRemoveAfter($itemId, $cart));

        return response()->json([
            'code' => 0,
            'message' => '移除商品成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Set cart item selection status.
     *
     * @param SetCartItemSelectionRequest $request
     * @return JsonResponse
     *
     * @throws CartClientException
     * @throws \Throwable
     */
    public function updateItemSelection(SetCartItemSelectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cart = ShoppingCart::setCartItemSelection(
            $validated['item_id'],
            $validated['selected']
        );

        return response()->json([
            'code' => 0,
            'message' => '商品选中状态已更新',
            'data' => new CartResource($cart),
        ]);
    }

    /**
     * 应用优惠券
     *
     * @param ApplyCouponRequest $request
     * @return JsonResponse
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $couponCode = $request->validated('code');

        try {
            if (strlen($couponCode)) {
                event(new CartApplyCouponBefore($couponCode));

                ShoppingCart::setCouponCode($couponCode)->collectTotals();

                $cart = ShoppingCart::getCart();

                if ($cart->coupon_code === $couponCode) {
                    event(new CartApplyCouponAfter(couponCode: $couponCode, cart: $cart));

                    return response()->json([
                        'code' => 0,
                        'message' => '应用优惠券成功',
                    ]);
                }
            }

            return response()->json([
                'code' => ErrorConst::ERROR_INVALID_COUPON_CODE->value,
                'message' => ErrorConst::errorMessage(ErrorConst::ERROR_INVALID_COUPON_CODE->value),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => ErrorConst::ERROR_CANNOT_APPLY_COUPON->value,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 移除优惠券
     *
     * @return JsonResponse
     *
     * @throws CartServerException
     */
    public function removeCoupon(): JsonResponse
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
