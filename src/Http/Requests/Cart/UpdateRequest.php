<?php

namespace Fayne\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Fayne\ShoppingCart\Models\ShoppingCartItem;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo 可自行优化
        $cartItem = ShoppingCartItem::with('cart')->find($this->input('item_id'));

        return $cartItem && $cartItem->cart && (app()->bound(CartContextResolver::class) || (string) $cartItem->cart->customer_id === (string) auth()->id()) && $cartItem->cart->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cartItemsTable = config('shopping-cart.models.cart_items.table', 'cart_items');

        return [
            'item_id' => "required|string|exists:$cartItemsTable,id",
            'quantity' => 'required|integer|min:1',
        ];
    }
}
