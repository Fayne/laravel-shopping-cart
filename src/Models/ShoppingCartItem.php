<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
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
    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shopping_cart_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cart_id',
        'itemable_id',
        'itemable_type',
        'quantity',
        'price',
        'name',
        'sku',
        'type',
        'weight',
        'total_weight',
        'base_total_weight',
        'base_price',
        'custom_price',
        'total',
        'base_total',
        'discount_percent',
        'discount_amount',
        'base_discount_amount',
        'additional',
        'selected',
        'coupon_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'additional' => 'array',
        'selected' => 'bool',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'custom_price' => 'decimal:2',
        'total' => 'decimal:2',
        'base_total' => 'decimal:2',
        'weight' => 'decimal:2',
        'total_weight' => 'decimal:2',
        'base_total_weight' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'base_discount_amount' => 'decimal:2',
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
        if ($this->itemable && method_exists($this->itemable, 'getPrice')) {
            return $this->itemable->getPrice();
        }
        
        // 如果没有关联对象或方法，返回当前价格
        return $this->price ?? 0.0;
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

    /**
     * 获取商品ID（兼容性访问器）
     *
     * @return mixed
     */
    public function getProductIdAttribute()
    {
        return $this->itemable_id;
    }

    /**
     * 获取选项（兼容性访问器）
     *
     * @return array|null
     */
    public function getOptionsAttribute()
    {
        return $this->additional;
    }

    /**
     * 获取是否选中（兼容性访问器）
     *
     * @return bool
     */
    public function getIsSelectedAttribute()
    {
        return $this->selected;
    }

    /**
     * 计算小计
     *
     * @return float
     */
    public function getSubtotal()
    {
        return $this->quantity * $this->price;
    }

    /**
     * 检查是否有选项
     *
     * @return bool
     */
    public function hasOptions()
    {
        return !empty($this->additional);
    }

    /**
     * 获取选项值
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption($key, $default = null)
    {
        return $this->additional[$key] ?? $default;
    }

    /**
     * 检查商品和选项是否匹配
     *
     * @param int $productId
     * @param array|null $options
     * @return bool
     */
    public function matches($productId, $options = null)
    {
        if ($this->itemable_id !== $productId) {
            return false;
        }

        if (empty($options) && empty($this->additional)) {
            return true;
        }

        if (empty($options) || empty($this->additional)) {
            return false;
        }

        return $this->additional == $options;
    }

    /**
     * 更新数量
     *
     * @param int $quantity
     * @return void
     */
    public function updateQuantity($quantity)
    {
        $this->quantity = $quantity;
        $this->save();
    }

    /**
     * 增加数量
     *
     * @param int $amount
     * @return void
     */
    public function incrementQuantity($amount = 1)
    {
        $this->quantity += $amount;
        $this->save();
    }

    /**
     * 减少数量
     *
     * @param int $amount
     * @return void
     */
    public function decrementQuantity($amount = 1)
    {
        $this->quantity = max(0, $this->quantity - $amount);
        $this->save();
    }

    /**
     * 切换选中状态
     *
     * @return void
     */
    public function toggleSelection()
    {
        $this->selected = !$this->selected;
        $this->save();
        $this->refresh();
    }

    /**
     * 设置选中状态
     *
     * @param bool $selected
     * @return void
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
        $this->save();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // 确保 selected 字段包含在数组中
        if (!isset($array['selected'])) {
            $array['selected'] = $this->selected;
        }
        
        return $array;
    }
}
