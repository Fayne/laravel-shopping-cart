<?php

namespace Fayne\ShoppingCart\Listeners;

use Fayne\ShoppingCart\Facades\ShoppingCart;

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
