<?php

namespace MobileNowGroup\ShoppingCart\Events;

use Illuminate\Support\Collection;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * 购物车清空事件
 */
class CartCleared
{
    public ShoppingCart $cart;
    public Collection $items;

    public function __construct(ShoppingCart $cart, Collection $items)
    {
        $this->cart = $cart;
        $this->items = $items;
    }
}