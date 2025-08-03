<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property bool $selected
 * @property int $quantity
 * @property float $price
 * @property float $total
 * @property float $discount_amount
 * @property float $base_discount_amount
 * @property float $weight
 * @property float $total_weight
 * @property float $base_total_weight
 * @property float $base_total
 * @property int $cart_id
 * @property int $itemable_id
 * @property string $itemable_type
 */
class ShoppingCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'name',
        'description',
        'image',
        'options',
        'is_selected',
    ];

    protected $casts = [
        'additional' => 'array',
        'selected' => 'bool',
        'options' => 'array',
        'is_selected' => 'boolean',
        'quantity' => 'integer',
        'price' => 'decimal:2',
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
    public function itemable(): MorphTo
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

    /**
     * 获取加入购物车时的价格
     *
     * @return float
     */
    public function getOriginalPriceAttribute(): float
    {
        return $this->price;
    }

    /**
     * 获取商品的最新价格
     *
     * @return float
     */
    public function getLatestPriceAttribute(): float
    {
        return $this->itemable->getPrice(); // 假设 product 关联了最新的价格
    }

    /**
     * 计算当前商品的总价（基于最新价格）
     *
     * @return float|int
     */
    public function getTotalPriceAttribute(): float|int
    {
        return $this->quantity * $this->latest_price;
    }

    /**
     * 计算加入购物车时的总价
     *
     * @return float|int
     */
    public function getOriginalTotalPriceAttribute(): float|int
    {
        return $this->quantity * $this->original_price;
    }
}
