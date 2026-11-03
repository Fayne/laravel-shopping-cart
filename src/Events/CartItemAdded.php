<?php

namespace Fayne\ShoppingCart\Events;

use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

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