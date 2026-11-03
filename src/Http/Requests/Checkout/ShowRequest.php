<?php

namespace Fayne\ShoppingCart\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Fayne\ShoppingCart\Facades\ShoppingCart;
use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;

class ShowRequest extends FormRequest
{
    public ?CartModel $cart;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->cart = ShoppingCart::getCart(true);

        //@todo  laravel 11 可改成：Context::add('cart', $cart);
        return $this->cart && ! $this->cart->is_temp;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
