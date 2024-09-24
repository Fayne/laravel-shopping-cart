<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * get the checkout page for cart after
 */
class ShowAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ?ShoppingCart $cart = null, public ?Authenticatable $user = null) {
        $this->cart = $cart ?? CartPackage::getCart();
        $this->user = $user ?? auth()->user();
    }
}
