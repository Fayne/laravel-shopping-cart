<?php

namespace Fayne\ShoppingCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Fayne\ShoppingCart\ShoppingCart
 *
 * @see \Fayne\ShoppingCart\ShoppingCart
 */
class ShoppingCart extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'shopping-cart';
    }
}
