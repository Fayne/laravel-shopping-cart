<?php

namespace Fayne\ShoppingCart\Events;

use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

/**
 * 购物车商品更新事件
 */
class CartItemUpdated
{
    public ShoppingCart $cart;
    public ShoppingCartItem $item;
    public int $oldQuantity;

    public function __construct(ShoppingCart $cart, ShoppingCartItem $item, int $oldQuantity)
    {
        $this->cart = $cart;
        $this->item = $item;
        $this->oldQuantity = $oldQuantity;
    }
}