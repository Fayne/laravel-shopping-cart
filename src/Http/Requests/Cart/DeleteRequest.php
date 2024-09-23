<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;

class DeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $cart = ShoppingCart::getCart();

        return (bool) $cart->is_temp === false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
        ];
    }
}
