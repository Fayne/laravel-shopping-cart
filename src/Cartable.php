<?php

namespace MobileNowGroup\ShoppingCart;

interface Cartable
{
    public function getPrice(): float;

    public function getSku(): string;

    public function getName(): string;

    public function getWeight(): float;
}
