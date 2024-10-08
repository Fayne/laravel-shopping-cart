<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Events\Traits\CartEvent;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * controller(结算):选中部分商品进行结算之前的事件
 */
class ToggleBuyBefore
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $type,
        public bool $selected,
        public ?int $itemId = null,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}
}
