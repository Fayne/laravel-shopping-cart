<?php

namespace Fayne\ShoppingCart\Models;

use Fayne\ShoppingCart\Support\CartSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property string $id
 * @property int $user_id
 * @property int $status
 * @property string $currency
 * @property float $subtotal_amount
 * @property float $discount_amount
 * @property float $tax_amount
 * @property float $shipping_amount
 * @property float $total_amount
 * @property array|null $metadata
 * @property CarbonImmutable|null $expires_at
 * @property Collection<int, CheckoutItem> $items
 */
class Checkout extends Model
{
    use \Fayne\ShoppingCart\Traits\HasCartOwnership;

    protected static function cartOwnerColumn(): string
    {
        return 'user_id';
    }

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
     * @var list<string>
     */
    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'expires_at' => 'immutable_datetime', 'status' => 'integer'];

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
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeOwn(Builder $query)
    {
        return $query->where('user_id', (static::cartContext()?->customerId() ?? auth()->id()));
    }

    /**
     * Scope a query to only include pending checkouts.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope a query to only include active (non-expired) checkouts.
     *
     * @param  Builder  $query
     * @return Builder
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
        return !$this->expires_at || $this->expires_at->isPast();
    }

    /**
     * Check if checkout can proceed to payment/order creation.
     *
     * @return bool
     */
    public function canProceed(): bool
    {
        if ($this->status !== 0 || $this->isExpired()) {
            return false;
        }

        // Check if items are still available
        foreach ($this->items as $item) {
            // Check if the related cartable item exists and can be purchased
            // Assuming itemable relationship exists or can be resolved via product_id/sku_id
            // For now, let's assume we can resolve the SKU via sku_id
            if ($item->sku_id) {
                $sku = CartSku::query()->find($item->sku_id);

                if (!($sku instanceof \Fayne\ShoppingCart\Contracts\Cartable) || !$sku->canBePurchased()) {
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
                    'discount_amount' => $item->discount_amount,
                    'total_amount' => round($item->price_snapshot * $item->quantity - $item->discount_amount, 2),
                ];
            })->toArray(),
        ];
    }
}
