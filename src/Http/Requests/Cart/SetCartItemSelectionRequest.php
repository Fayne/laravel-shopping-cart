<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class SetCartItemSelectionRequest extends FormRequest
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
        $cartItemsTable = config('shopping-cart.models.cart_items.table', 'cart_items');
        
        return [
            'selected' => 'required|boolean',
            'item_id' => "required|string|exists:$cartItemsTable,id",
        ];
    }
}
