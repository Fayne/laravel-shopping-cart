<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToggleBuyBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $type, public bool $selected, public int $itemId) {}
}
