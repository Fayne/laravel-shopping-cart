<?php

namespace MobileNowGroup\ShoppingCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MobileNowGroup\ShoppingCart\ShoppingCart
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
