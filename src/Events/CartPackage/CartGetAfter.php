<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

class CartGetAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?ShoppingCart $cart;

    /**
     * Create a new event instance.
     */
    public function __construct(?ShoppingCart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
