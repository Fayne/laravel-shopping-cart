<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ToggleBuyAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ToggleBuyBefore;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;
use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;

class ToggleBuyRequest extends FormRequest
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
            'type' => 'required|string|in:single,all',
            'selected' => 'required|boolean',
            'item_id' => 'required_if:type,single|exists:cart_items,id',
        ];
    }

    public function handle()
    {
        ToggleBuyBefore::dispatch(
            $this->validated('type'),
            $this->validated('selected'),
            $this->validated('item_id')
        );

        ShoppingCart::toggleBuy(
            $this->validated('type'),
            $this->validated('selected'),
            $this->validated('item_id')
        );

        $cart = ShoppingCart::getCart();

        ToggleBuyAfter::dispatch($cart);

        return response()->json([
            'code' => 0,
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }
}
