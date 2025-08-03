<?php

namespace MobileNowGroup\ShoppingCart\Events;

use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

/**
 * 购物车商品添加事件
 */
class CartItemAdded
{
    public ShoppingCart $cart;
    public ShoppingCartItem $item;

    public function __construct(ShoppingCart $cart, ShoppingCartItem $item)
    {
        $this->cart = $cart;
        $this->item = $item;
    }
}