<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

class CartUpdateItemAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ShoppingCartItem $item;

    /**
     * Create a new event instance.
     */
    public function __construct(ShoppingCartItem $item)
    {
        $this->item = $item;
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
