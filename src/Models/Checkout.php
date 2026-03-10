<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->out_trade_no)) {
                $generatorClass = config('shopping-cart.order_number_generator_class');
                $model->out_trade_no = (new $generatorClass)();
            }
        });
    }

    /**
     * Get the items for the checkout.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CheckoutItem::class);
    }

    /**
     * Scope a query to only include owed checkouts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwn(Builder $query)
    {
        return $query->where('user_id', auth()->id());
    }

    /**
     * Scope a query to only include pending checkouts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope a query to only include active (non-expired) checkouts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
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

    /**
     * 将 Checkout 转换为创建 Order 所需的数组数据
     *
     * @return array
     */
    public function toOrderArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount,
            'total_amount' => $this->total_amount,
            'metadata' => $this->metadata,
            'checkout_id' => $this->id,
            'items' => $this->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'sku_id' => $item->sku_id,
                    'name_snapshot' => $item->name_snapshot,
                    'price_snapshot' => $item->price_snapshot,
                    'quantity' => $item->quantity,
                    'total_amount' => $item->price_snapshot * $item->quantity,
                ];
            })->toArray(),
        ];
    }
}
