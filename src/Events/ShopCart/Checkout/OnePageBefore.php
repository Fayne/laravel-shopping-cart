<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Events\Traits\CartEvent;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * controller(结算):获取立即购买的结算页数据之前的事件
 */
class OnePageBefore
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ?ShoppingCart $cart = null, public ?Authenticatable $user = null) {}
}
