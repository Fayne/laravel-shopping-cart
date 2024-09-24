<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemIsBuyNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public bool $is_buy_now, public int $product_sku_id, public int $quantity)
    {
    }
}
