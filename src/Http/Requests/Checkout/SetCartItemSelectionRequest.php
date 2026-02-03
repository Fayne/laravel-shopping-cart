<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests\Checkout;

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
        return [
            'selected' => 'required|boolean',
            'item_id' => 'required:exists:cart_items,id',
        ];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \MobileNowGroup\ShoppingCart\Exceptions\CartClientException
     * @throws \Throwable
     */
    public function handle(): JsonResponse
    {
        $cart = ShoppingCart::setCartItemSelection(
            $this->validated('item_id'),
            $this->validated('selected')
        );

        return response()->json([
            'code' => 0,
            'data' => new CartResource($cart),
        ]);
    }
}
