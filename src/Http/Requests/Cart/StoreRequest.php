<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemAddAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemAddBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemIsBuyNow;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'is_buy_now' => 'nullable|boolean',
            'product_sku_id' => 'required|integer|exists:product_skus,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function handle(): JsonResponse
    {
        $isBuyNow = $this->validated('is_buy_now', false);
        if ($isBuyNow) {
            ShoppingCart::deactivateCurrentCartIfBuyNowIsActive();
            event(new CartItemIsBuyNow(
                isBuyNow: $this->validated('is_buy_now'),
                productSkuId: $this->validated('product_sku_id'),
                quantity: $this->validated('quantity')
            ));
        }

        event(new CartItemAddBefore(
            isBuyNow: $isBuyNow,
            productSkuId: $this->validated('product_sku_id'),
            quantity: $this->validated('quantity')
        ));

        try {
            $productSkuModel = config('shopping-cart.models.skus.model');
            $product = $productSkuModel::find($this->validated('product_sku_id'));
            $cart = ShoppingCart::addProduct(
                $product,
                $this->validated('quantity'),
                $isBuyNow
            );

            event(new CartItemAddAfter(
                isBuyNow: $isBuyNow,
                productSkuId: $this->validated('product_sku_id'),
                quantity: $this->validated('quantity'),
                cart: $cart
            ));

            return response()->json([
                'code' => 0,
                'message' => '商品已成功添加到购物车。',
                'data' => $cart ? new CartResource($cart) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => null,
                'message' => $e->getMessage(),
                'code' => ErrorConst::CODE_ERROR,
            ]);
        }
    }
}
