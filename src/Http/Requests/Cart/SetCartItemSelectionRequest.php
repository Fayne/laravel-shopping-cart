<?php

namespace Fayne\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

class SetCartItemSelectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ShoppingCartItem::whereKey($this->input('item_id'))->whereHas('cart', fn ($query) => $query->when(!app()->bound(CartContextResolver::class), fn ($query) => $query->where('customer_id', auth()->id()))->where('is_active', true))->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $cartItemsTable = config('shopping-cart.models.cart_items.table', 'cart_items');

        return [
            'selected' => 'required|boolean',
            'item_id' => "required|string|exists:$cartItemsTable,id",
        ];
    }
}
