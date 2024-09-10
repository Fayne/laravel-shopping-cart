<?php

namespace MobileNowGroup\ShoppingCart\Traits;

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
     * @return $this
     */
    public function setCouponCode(string $code)
    {
        $cart = $this->getCart();

        $cart->coupon_code = $code;

        $cart->save();

        return $this;
    }

    /**
     * Remove coupon code from the cart.
     *
     * @return $this
     */
    public function removeCouponCode(): static
    {
        $cart = $this->getCart();

        $cart->coupon_code = null;

        $cart->save();

        return $this;
    }
}
