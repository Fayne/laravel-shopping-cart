<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShoppingCartItem extends Model
{
    protected $casts = [
        'additional' => 'array',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];
    /**
     * Create a new instance of the model.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('shopping-cart.models.cart_items.table');
    }

    /**
     * Relation polymorphic, inverse one-to-one or many relationship.
     */
    public function itemable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
    /**
     * @return HasOne
     */
    public function cart(): HasOne
    {
        return $this->hasOne(ShoppingCart::class, 'id', 'cart_id');
    }
}
