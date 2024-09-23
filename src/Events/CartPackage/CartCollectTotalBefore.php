<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

class CartCollectTotalBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Builder|Model $cart;

    /**
     * Create a new event instance.
     */
    public function __construct(Builder|Model $cart)
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
