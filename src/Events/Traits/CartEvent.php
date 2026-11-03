<?php

namespace Fayne\ShoppingCart\Events\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Fayne\ShoppingCart\Facades\ShoppingCart as CartPackage;
use Fayne\ShoppingCart\Models\ShoppingCart;

trait CartEvent
{
    public function getCart(): ?ShoppingCart
    {
        return $this->cart ?? CartPackage::getCart();
    }

    public function getUser(): ?Authenticatable
    {
        return $this->user ?? auth()->user();
    }

}
