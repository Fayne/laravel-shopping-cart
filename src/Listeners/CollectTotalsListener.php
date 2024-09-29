<?php

namespace MobileNowGroup\ShoppingCart\Listeners;

class CollectTotalsListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        ShoppingCart::collectTotals();
    }
}
