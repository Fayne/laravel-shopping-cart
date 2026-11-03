<?php

namespace Fayne\ShoppingCart\Events;

use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Models\ShoppingCart;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

/** Synchronous host validation/reservation; throw to roll back checkout. */
class CheckoutValidating
{
    public function __construct(public ShoppingCart $cart, public ShoppingCartItem $item, public Cartable $sku) {}
}
