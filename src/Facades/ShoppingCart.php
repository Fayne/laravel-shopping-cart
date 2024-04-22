<?php

namespace MobileNowGroup\ShoppingCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MobileNowGroup\ShoppingCart\ShoppingCart
 */
class ShoppingCart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MobileNowGroup\ShoppingCart\ShoppingCart::class;
    }
}
