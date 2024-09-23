<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartGetBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $isCheckout;

    /**
     * Create a new event instance.
     */
    public function __construct(bool $isCheckout = false)
    {
        $this->isCheckout = $isCheckout;
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
