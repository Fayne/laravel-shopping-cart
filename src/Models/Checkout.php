<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkout extends Model
{
    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'checkout';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = false;

    /**
     * Get the items for the checkout.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CheckoutItem::class);
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        $expirationMinutes = config('shopping-cart.checkout.expiration', 30);

        return $this->created_at->addMinutes($expirationMinutes)->isPast();
    }

    /**
     * Check if checkout can proceed to payment/order creation.
     * 
     * @return bool
     */
    public function canProceed(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        // Check if items are still available
        foreach ($this->items as $item) {
            // Check if the related cartable item exists and can be purchased
            // Assuming itemable relationship exists or can be resolved via product_id/sku_id
            // For now, let's assume we can resolve the SKU via sku_id
            if ($item->sku_id) {
                $skuClass = config('shopping-cart.models.skus.model');
                $sku = $skuClass::find($item->sku_id);
                
                if (!$sku || !$sku->canBePurchased()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function transformToOrder(): mixed
    {
        $class = config('shopping-cart.order_class');

        return $class::createFromCheckout($this);
    }
}
