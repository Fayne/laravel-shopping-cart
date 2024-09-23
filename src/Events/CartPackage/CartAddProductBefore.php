<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartAddProductBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $item;
    public int $quantity;

    /**
     * Create a new event instance.
     */
    public function __construct(Model $item, int $quantity)
    {
        $this->item = $item;
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
