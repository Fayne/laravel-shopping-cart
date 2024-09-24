<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Events\Traits\CartEvent;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * After entering the checkout page from Buy Now, listen for the return operation of the checkout page
 * after data processing
 */
class BackFromOnePageAfter
{
    use CartEvent, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ?ShoppingCart $cart = null, public ?Authenticatable $user = null) {}
}
