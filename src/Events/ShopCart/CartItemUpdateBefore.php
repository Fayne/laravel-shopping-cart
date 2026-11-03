<?php

namespace Fayne\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Fayne\ShoppingCart\Events\Traits\CartEvent;
use Fayne\ShoppingCart\Models\ShoppingCart;

//controller:更新购物车商品数量之前的事件

class CartItemUpdateBefore
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int|string $itemId,
        public int $quantity,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}
}
