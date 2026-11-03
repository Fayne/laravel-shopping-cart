<?php

namespace Fayne\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $checkout_id
 * @property string $product_id
 * @property string $sku_id
 * @property string $name_snapshot
 * @property float $price_snapshot
 * @property float $discount_amount
 * @property int $quantity
 */
class CheckoutItem extends Model
{
    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'checkout_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string, string>
     */
    protected $guarded = [];

    /**
     * Get the checkout that owns the item.
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }
}
