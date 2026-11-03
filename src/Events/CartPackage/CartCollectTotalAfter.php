<?php

namespace Fayne\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Fayne\ShoppingCart\Events\Traits\CartEvent;
use Fayne\ShoppingCart\Models\ShoppingCart;

/**
 * Class CartCollectTotalAfter
 * 计算购物车总价之后的事件
 */
class CartCollectTotalAfter
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ?ShoppingCart $cart = null, public ?Authenticatable $user = null) {}
}
