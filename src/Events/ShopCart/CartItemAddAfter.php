<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

class CartItemAddAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ShoppingCart $cart, Builder|Cartable|null $result) {}
}
