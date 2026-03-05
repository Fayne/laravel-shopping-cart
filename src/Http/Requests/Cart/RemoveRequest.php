<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

class RemoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //@todo 自行优化
        $cartItem = ShoppingCartItem::with('cart')->find($this->input('item_id'));

        if (!$cartItem) {
            abort(404, __('shopping_cart::messages.item_not_found'));
        }

        return $cartItem->cart->customer_id === auth()->id() && (bool) $cartItem->cart->is_temp === false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'item_id' => 'required|string',
        ];
    }
}
