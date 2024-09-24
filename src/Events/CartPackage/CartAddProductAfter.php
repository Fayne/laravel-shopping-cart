<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Contracts\CollectTotalEvent;
use MobileNowGroup\ShoppingCart\Events\Traits\CartEvent;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

/**
 * Class CartAddProductAfter for cartPackage
 * add product to cart after
 */
class CartAddProductAfter implements CollectTotalEvent
{
    use CartEvent, Dispatchable, InteractsWithSockets,SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Cartable $item,
        public ?ShoppingCart $cart = null,
        public ?Authenticatable $user = null
    ) {}

}
