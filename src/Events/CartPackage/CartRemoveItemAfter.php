<?php

namespace Fayne\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Fayne\ShoppingCart\Contracts\CollectTotalEvent;
use Fayne\ShoppingCart\Events\Traits\CartEvent;
use Fayne\ShoppingCart\Models\ShoppingCart;

/**
 * 移除购物车某商品之后的事件
 */
class CartRemoveItemAfter implements CollectTotalEvent
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int|string $itemId,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}
}
