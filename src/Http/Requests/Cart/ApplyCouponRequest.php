<?php

namespace MobileNowGroup\ShoppingCart\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart;

class ApplyCouponRequest extends FormRequest
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
            'code' => 'required',
        ];
    }

    public function handle()
    {
        $couponCode = $this->validated('code');

        try {
            if (strlen($couponCode)) {
                ShoppingCart::setCouponCode($couponCode)->collectTotals();

                if (ShoppingCart::getCart()->coupon_code == $couponCode) {
                    return response()->json([
                        'code' => 0,
                        'message' => '应用优惠券成功',
                    ]);
                }
            }

            return response()->json([
                'code' => ErrorConst::ERROR_INVALID_COUPON_CODE,
                'message' => ErrorConst::ERROR_MESSAGE[ErrorConst::ERROR_INVALID_COUPON_CODE],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => ErrorConst::ERROR_CANNOT_APPLY_COUPON,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
