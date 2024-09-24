<?php

namespace MobileNowGroup\ShoppingCart\Events\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use MobileNowGroup\ShoppingCart\Facades\ShoppingCart as CartPackage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

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
