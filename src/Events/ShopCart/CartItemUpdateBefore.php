<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemUpdateBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $cartItemId;
    public int $quantity;

    /**
     * Create a new event instance.
     */
    public function __construct(int $cartItemId, int $quantity)
    {
        $this->cartItemId = $cartItemId;
        $this->quantity = $quantity;
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
