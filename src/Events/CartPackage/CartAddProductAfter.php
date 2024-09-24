<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;

/**
 * Class CartAddProductAfter for cartPackage
 * add product to cart after
 */
class CartAddProductAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Cartable $item,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {
        $this->cart = $cart ?? CartPackage::getCart();
        $this->user = $user ?? auth()->user();
    }

}
