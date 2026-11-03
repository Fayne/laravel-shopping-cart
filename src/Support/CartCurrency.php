<?php

namespace Fayne\ShoppingCart\Support;

use Fayne\ShoppingCart\Contracts\CartCurrencyResolver;

final class CartCurrency
{
    public static function current(): string
    {
        return app()->bound(CartCurrencyResolver::class)
            ? app(CartCurrencyResolver::class)->current()
            : config('shopping-cart.checkout.currency', 'CNY');
    }
}
