<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemAddAfter
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public null|Builder|Model $result;

    /**
     * Create a new event instance.
     */
    public function __construct(Builder|Model|null $result)
    {
        $this->result = $result;
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
