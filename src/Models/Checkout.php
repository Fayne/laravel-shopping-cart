<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Model;

class Checkout extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'checkout';

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
