<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

class User
{
    /**
     * @return HasOne
     */
    public function cart(): HasOne
    {
        return $this->hasOne(ShoppingCart::class, 'customer_id')
            ->where('customer_id', auth()->id())
            ->where('is_active', 1);
    }
}
