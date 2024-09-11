<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_buy_now' => 'required|boolean',
            'product_sku_id' => 'required|integer|exists:product_skus,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function handle(): JsonResponse
    {
        if ($this->validated('is_buy_now')) {
            Event::dispatch('shop.item.buy-now', $this->validated('product_sku_id'));
        }

        Event::dispatch('checkout.cart.item.add.before', $this->validated('product_sku_id'));

        try {
            $result = ShoppingCart::add($this->validated('product_sku_id'), $this->validated('quantity'));

            Event::dispatch('checkout.cart.item.add.after', $result);

            ShoppingCart::collectTotals();

            $cart = ShoppingCart::getCart();

            return response()->json([
                'code' => 0,
                'message' => '商品已成功添加到购物车。',
                'data' => $cart ? new CartResource($cart) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ]);
        }
    }
}
