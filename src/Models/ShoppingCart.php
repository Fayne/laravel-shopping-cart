<?php

namespace Fayne\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Fayne\ShoppingCart\Contracts\Cartable;
use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\CartServerException;
use Illuminate\Database\Eloquent\Collection;

/**
 * ShoppingCart
 *
 * @property float $discount
 * @property float $base_discount
 * @property float $sub_total
 * @property float $base_sub_total
 * @property float $grand_total
 * @property float $base_grand_total
 * @property int $items_count
 * @property int $items_qty
 * @property string|null $coupon_code
 * @property string $customer_id
 * @property bool $is_temp
 * @property string $id
 * @property bool $is_active
 * @property array|null $metadata
 * @property string|null $coupon_code
 * @property Collection<int, ShoppingCartItem> $items
 */
class ShoppingCart extends Model
{
    use \Fayne\ShoppingCart\Traits\HasCartOwnership;

    protected static function cartOwnerColumn(): string
    {
        return 'customer_id';
    }

    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shopping_carts';

    protected $hidden = ['active_owner'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'customer_name',
        'session_id',
        'metadata',
        'coupon_code',
        'is_active',
        'is_temp',
        'items_count',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_temp' => 'bool',
        'is_active' => 'bool',
        'metadata' => 'array',
    ];

    /**
     * @var list<string>
     */
    protected $with = [
        'items',
    ];

    /**
     * Create a new instance of the model.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('shopping-cart.models.carts.table');
    }

    /**
     * To get relevant associated items with the cart instance
     *

     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class, 'cart_id');
    }

    /**
     * Calculate price by quantity of items.
     */
    public function calculatedPriceByQuantity(): float
    {
        $totalPrice = 0;
        /** @var Collection<int, ShoppingCartItem> $items */
        $items = $this->items()->get();
        foreach ($items as $item) {
            $totalPrice += (int) $item->quantity * $item->latest_price;
        }

        return $totalPrice;
    }

    /**
     * Store cart item in cart.
     *
     * @param  Cartable $item
     * @param  int      $quantity
     * @return $this
     *
     * @throws CartClientException|CartServerException
     */
    public function storeItem(Cartable $item, int $quantity): static
    {
        /** @var ShoppingCartItem|null $cartItem */
        $cartItem = $this->items()->firstWhere('itemable_id', $item->getKey());
        if ($cartItem) {
            $cartItem->quantity += $quantity;

            $cartItem->total = $cartItem->lineAmount((int) $cartItem->quantity);
            $cartItem->base_total = $cartItem->lineAmount((int) $cartItem->quantity);
            $cartItem->total_weight = $cartItem->weight * $cartItem->quantity;
            $cartItem->base_total_weight = $cartItem->weight * $cartItem->quantity;

            $cartItem->save();

            return $this;
        }
        $this->items()->create([
            'itemable_id' => $item->getKey(),
            'itemable_type' => get_class($item),
            'quantity' => $quantity,
            'price' => ($item->getPriceInMinorUnits() / 100),
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'base_price' => ($item->getPriceInMinorUnits() / 100),
            'total' => ($item->getPriceInMinorUnits() * $quantity) / 100,
            'base_total' => ($item->getPriceInMinorUnits() * $quantity) / 100,
            'weight' => $item->getWeight() ?? 0,
            'selected' => true,
            'total_weight' => ($item->getWeight() ?? 0) * $quantity,
            'base_total_weight' => ($item->getWeight() ?? 0) * $quantity,
        ]);

        return $this;
    }

    /**
     * Check if cart has a product with given ID.
     */
    public function hasProduct(int|string $productId): bool
    {
        return $this->items()->where('itemable_id', $productId)->exists();
    }

    /**
     * Get the total amount of the cart.
     */
    public function getTotalAmount(): float
    {
        return $this->items()->sum('total');
    }

    /**
     * Clear all items from the cart.
     */
    public function clearItems(): void
    {
        $this->items()->delete();
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }

    /**
     * Get the total number of items in the cart.
     */
    public function getTotalItems(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get the count of unique products in the cart.
     */
    public function getUniqueProductCount(): int
    {
        return $this->items()->count();
    }

    /**
     * Find item by product ID.
     */
    public function findItemByProductId(int|string $productId): ?ShoppingCartItem
    {
        /** @var ShoppingCartItem|null $item */
        $item = $this->items()->where('itemable_id', $productId)->first();

        return $item;
    }

    /**
     * Find item by product ID and options.
     */
    public function findItemByProductIdAndOptions(int|string $productId, array $options): ?ShoppingCartItem
    {
        /** @var ShoppingCartItem|null $item */
        $item = $this->items()
            ->where('itemable_id', $productId)
            ->where('additional', json_encode($options))
            ->first();

        return $item;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['items'] = $this->items->toArray();

        return $array;
    }

}
