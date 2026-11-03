<?php

namespace Fayne\ShoppingCart\Events;

use Fayne\ShoppingCart\Models\ShoppingCart;

/**
 * 购物车总价计算事件
 */
class CartTotalCalculated
{
    public ShoppingCart $cart;
    public float $total;
    public int $itemCount;

    public function __construct(ShoppingCart $cart, float $total, int $itemCount)
    {
        $this->cart = $cart;
        $this->total = $total;
        $this->itemCount = $itemCount;
    }
}