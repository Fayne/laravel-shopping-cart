<?php

namespace MobileNowGroup\ShoppingCart\Traits;

use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;

/**
 * Cart coupons. In this trait, you will get all sorted collections of
 * methods which can be use for coupon in cart.
 *
 * Note: This trait will only work with the Cart facade. Unless and until,
 * you have all the required repositories in the parent class.
 */
trait CartCoupons
{
    /**
     * Set coupon code to the cart.
     *
     * @param string $code
     * @param callable|null $callback Optional callback to handle custom coupon validation and discount calculation.
     * @return $this
     *
     * @throws CartServerException
     */
    public function setCouponCode(string $code, ?callable $callback = null): static
    {
        $cart = $this->getCart();

        $cart->coupon_code = $code;

        if (is_callable($callback)) {
            // 调用回调函数并传递优惠券代码和当前购物车实例
            $isValid = $callback($code, $cart, $this);

            if (!$isValid) {
                throw new CartServerException(ErrorConst::ERROR_COUPON_INVALID->value);
            }
        }

        $cart->save();

        return $this;
    }

    /**
     * Remove coupon code from the cart.
     *
     * @param callable|null $callback
     * @return $this
     *
     * @throws CartServerException
     */
    public function removeCouponCode(?callable $callback = null): static
    {
        $cart = $this->getCart();

        if (is_callable($callback)) {
            // 调用回调函数并传递优惠券代码和当前购物车实例
            $isValid = $callback($cart, $this);

            if (!$isValid) {
                throw new CartServerException(ErrorConst::ERROR_REMOVE_COUPON->value);
            }
        }
        // 移除优惠券代码
        $cart->coupon_code = null;

        // 保存购物车状态
        $cart->save();

        return $this;
    }
}
