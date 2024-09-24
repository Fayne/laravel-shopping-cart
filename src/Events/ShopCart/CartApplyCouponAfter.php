<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

class CartApplyCouponAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ShoppingCart $cart, public string $couponCode) {}
}
