<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Event;
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
            'cart_item_id' => 'required|integer|exists:shopping_cart_items,id',
            'qty' => 'required|integer|min:1',
        ];
    }

    public function handle()
    {
        $requestedQuantity = $this->validated('qty');

        $item = ShoppingCartItem::find($this->validated('cart_item_id'));

        Event::dispatch('checkout.cart.item.update.before', $this->validated('cart_item_id'));

        ShoppingCart::updateItems([
            'cart_item_id' => $this->validated('cart_item_id'),
            'qty' => $requestedQuantity,
        ]);

        Event::dispatch('checkout.cart.item.update.after', $item);

        ShoppingCart::collectTotals();

        $cart = ShoppingCart::getCart();

        return response()->json([
            'code' => 0,
            'message' => '购物车更新成功',
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
