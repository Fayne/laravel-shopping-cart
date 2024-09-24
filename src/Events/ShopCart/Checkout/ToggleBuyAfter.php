<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * Select some items for checkout in controller
 */
class ToggleBuyAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $type,
        public bool $selected,
        public int $itemId,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {
        $this->cart = $cart ?? CartPackage::getCart();
        $this->user = $user ?? auth()->user();
    }
}
