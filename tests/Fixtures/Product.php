<?php

namespace Fayne\ShoppingCart\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Fayne\ShoppingCart\Contracts\Cartable;

/** @property float $price */
class Product extends Model implements Cartable
{
    use HasUlids;
    protected $table = 'products';
    protected $guarded = [];
    public static function findByCodeOrFail(string $code): self
    {
        return static::where('sku', $code)->firstOrFail();
    }

    public function getPriceInMinorUnits(): int { return (int) round($this->price * 100); }
    public function getCurrency(): string { return 'CNY'; }
    public function getSku(): string { return 'sku'; }
    public function getName(): string { return 'Product'; }
    public function getWeight(): float|int|null { return 1; }
    public function canBePurchased(): bool { return true; }
}
