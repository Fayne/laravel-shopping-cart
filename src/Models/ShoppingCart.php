<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Exceptions\CartClientException;
use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;

class ShoppingCart extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * @var string[]
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
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class, 'cart_id');
    }

    /**
     * Calculate price by quantity of items.
     */
    public function calculatedPriceByQuantity(): int
    {
        $totalPrice = 0;
        foreach ($this->items()->get() as $item) {
            $totalPrice += (int) $item->quantity * (int) $item->itemable->getPrice();
        }

        return $totalPrice;
    }

    /**
     * Store cart item in cart.
     *
     * @param Cartable $item
     * @param int $quantity
     * @return $this
     *
     * @throws CartClientException|CartServerException
     */
    public function storeItem(Cartable $item, int $quantity): static
    {
        $cartItem = $this->items()->firstWhere('itemable_id', $item->getKey());
        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();

            return $this;
        }
        $this->items()->create([
            'itemable_id' => $item->getKey(),
            'itemable_type' => get_class($item),
            'quantity' => $quantity,
            'price' => $item->getPrice(),
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'base_price' => $item->getPrice(),
            'total' => $item->getPrice() * $quantity,
            'base_total' => $item->getPrice() * $quantity,
            'weight' => $item->getWeight() ?? 0,
            'total_weight' => ($item->getWeight() ?? 0) * $quantity,
            'base_total_weight' => ($item->getWeight() ?? 0) * $quantity,
        ]);

        return $this;
    }

}
