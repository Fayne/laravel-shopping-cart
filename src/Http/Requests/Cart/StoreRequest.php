<?php

namespace Fayne\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

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
            'code' => 'required|string|exists:product_skus,sku',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
