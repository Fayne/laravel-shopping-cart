<?php

namespace MobileNowGroup\ShoppingCart\Contracts;

use MobileNowGroup\ShoppingCart\CartItem;

interface Calculator
{
    public static function getAttribute(string $attribute, CartItem $cartItem);
}
