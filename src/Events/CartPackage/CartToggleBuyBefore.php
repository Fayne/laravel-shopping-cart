<?php

namespace MobileNowGroup\ShoppingCart\Events\CartPackage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartToggleBuyBefore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $type;
    public bool $selected;
    public ?int $itemId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $type, bool $selected, ?int $itemId)
    {
        $this->type = $type;
        $this->selected = $selected;
        $this->itemId = $itemId;
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
