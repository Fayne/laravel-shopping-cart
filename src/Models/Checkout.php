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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

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
        // @todo
    }

    /**
     * @return bool
     */
    public function canProceed(): bool
    {
        // @todo
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
