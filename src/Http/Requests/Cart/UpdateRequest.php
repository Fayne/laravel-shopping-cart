<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemUpdateAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemUpdateBefore;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //@todo 可自行优化
        $cartItem = ShoppingCartItem::with('cart')->find($this->input('cart_item_id'));

        return $cartItem->cart->customer_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cart_item_id' => 'required|integer|exists:cart_items,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function handle()
    {
        $requestedQuantity = $this->validated('quantity');

        event(new CartItemUpdateBefore(
            cartItemId: $this->validated('cart_item_id'),
            quantity: $this->validated('quantity')
        ));

        $cart = ShoppingCart::updateItems($this->validated('cart_item_id'), $requestedQuantity);

        // ShoppingCart::collectTotals();

        event(new CartItemUpdateAfter(
            cartItemId: $this->validated('cart_item_id'),
            quantity: $this->validated('quantity'),
            cart: $cart
        ));

        return response()->json([
            'code' => 0,
            'message' => '购物车更新成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
