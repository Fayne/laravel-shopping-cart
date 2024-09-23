<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartUpdateItemBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $itemId;
    public int $quantity;

    /**
     * Create a new event instance.
     */
    public function __construct(int $itemId, int $quantity)
    {
        $this->itemId = $itemId;
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
