<?php

namespace Fayne\ShoppingCart\Contracts;

/**
 * @method int|string getKey()
 * @method string getMorphClass()
 */
interface Cartable
{
    public function getPriceInMinorUnits(): int;

    public function getCurrency(): string;

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
