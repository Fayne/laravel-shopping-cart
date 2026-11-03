<?php

namespace Fayne\ShoppingCart\Contracts;

interface CartCurrencyResolver
{
    public function current(): string;
}
