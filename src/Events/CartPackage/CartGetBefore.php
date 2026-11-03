<?php

namespace Fayne\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Fayne\ShoppingCart\Events\Traits\CartEvent;
use Fayne\ShoppingCart\Models\ShoppingCart;

/**
 * 获取购物车信息之前的事件
 */
class CartGetBefore
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public bool $isCheckout = false,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}
}
