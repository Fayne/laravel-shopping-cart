<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\CollectTotalEvent;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * Update item in cart before
 */
class CartUpdateItemBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $itemId,
        public int $quantity,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {
        $this->cart = $cart ?? CartPackage::getCart();
        $this->user = $user ?? auth()->user();
    }
}
