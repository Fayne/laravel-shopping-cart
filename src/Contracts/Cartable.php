<?php

namespace MobileNowGroup\ShoppingCart\Contracts;

interface Cartable
{
    /**
     * @return float
     */
    public function getPrice(): float;

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return float|int|null
     */
    public function getWeight(): float|int|null;

    /**
     * Check if the item can be purchased.
     *
     * @return bool
     */
    public function canBePurchased(): bool;
}
