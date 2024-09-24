<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * add product to cart before
 */
class CartAddProductBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Cartable $item,
        public int $quantity,
        public bool $isBuyNow,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {
        $this->cart = $cart ?? CartPackage::getCart();
        $this->user = $user ?? auth()->user();
    }
}
