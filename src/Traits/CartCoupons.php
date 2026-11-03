<?php

namespace Fayne\ShoppingCart\Traits;

use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\ErrorConst;

trait CartCoupons
{
    public function setCouponCode(string $code, ?callable $callback = null): static
    {
        return $this->transaction(function () use ($code, $callback) {
            $cart = $this->getCart();
            if (!$cart) {
                throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST->value, httpCode: 404);
            }
            $callback ??= config('shopping-cart.coupon_validator');
            if (is_string($callback) && class_exists($callback)) {
                $callback = app($callback);
            }
            if (!is_callable($callback) || !$callback($code, $cart, $this)) {
                throw new CartClientException(ErrorConst::ERROR_COUPON_INVALID->value, httpCode: 422);
            }
            $cart->coupon_code = $code;
            $this->storage->updateCart($cart);
            $this->collectTotals();

            return $this;
        });
    }

    public function removeCouponCode(?callable $callback = null): static
    {
        return $this->transaction(function () use ($callback) {
            $cart = $this->getCart();
            if (!$cart) {
                throw new CartClientException(ErrorConst::ERROR_CART_NOT_EXIST->value, httpCode: 404);
            }
            if ($callback && !$callback($cart, $this)) {
                throw new CartClientException(ErrorConst::ERROR_REMOVE_COUPON->value, httpCode: 422);
            }
            $cart->items()->update(['discount_amount' => 0, 'base_discount_amount' => 0, 'discount_percent' => 0, 'coupon_code' => null]);
            $cart->coupon_code = null;
            $this->storage->updateCart($cart);
            $this->collectTotals();

            return $this;
        });
    }
}
