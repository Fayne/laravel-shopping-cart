<?php

namespace MobileNowGroup\ShoppingCart\Events\ShopCart;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartItemIsBuyNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $is_buy_now;
    public int $product_sku_id;
    public int $quantity;

    /**
     * Create a new event instance.
     */
    public function __construct(bool $is_buy_now, int $product_sku_id, int $quantity)
    {
        $this->is_buy_now = $is_buy_now;
        $this->product_sku_id = $product_sku_id;
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
