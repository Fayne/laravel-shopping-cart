<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\CollectTotalEvent;
use MobileNowGroup\ShoppingCart\Events\Traits\CartEvent;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

/**
 * 更新购物车商品数量之后的事件
 */
class CartUpdateItemAfter implements CollectTotalEvent
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ShoppingCartItem $item,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}
}
