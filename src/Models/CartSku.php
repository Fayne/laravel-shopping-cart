<?php

namespace Fayne\ShoppingCart\Models;

use Fayne\ShoppingCart\Contracts\Cartable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $sku
 * @property string $name
 * @property int $price_amount
 * @property string $currency
 * @property bool $is_active
 */
class CartSku extends Model implements Cartable
{
    use HasUlids;

    protected $table = 'shopping_cart_skus';

    protected $fillable = ['sku', 'name', 'price_amount', 'currency', 'is_active'];

    protected $casts = ['price_amount' => 'integer', 'is_active' => 'boolean'];

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriceInMinorUnits(): int
    {
        return $this->price_amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getWeight(): null
    {
        return null;
    }

    public function canBePurchased(): bool
    {
        return $this->is_active;
    }
}
