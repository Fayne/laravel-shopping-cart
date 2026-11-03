<?php

namespace Fayne\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Fayne\ShoppingCart\Events\Traits\CartEvent;
use Fayne\ShoppingCart\Models\ShoppingCart;

/**
 * controller(结算): 从Buy Now进入结帐页面后，监听结帐页面的返回操作，处理数据之后的事件
 */
class BackFromOnePageAfter
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ?ShoppingCart $cart = null, public ?Authenticatable $user = null) {}
}
