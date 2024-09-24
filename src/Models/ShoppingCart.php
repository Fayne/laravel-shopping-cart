<?php

namespace MobileNowGroup\ShoppingCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MobileNowGroup\ShoppingCart\Contracts\Cartable;
use MobileNowGroup\ShoppingCart\Exceptions\CartException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;

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
     * Store multiple items in cart.
     *
     * @throws CartException
     */
    public function storeItems(array $items): static
    {
        foreach ($items as $item) {
            $this->storeItem($item);
        }

        return $this;
    }

    /**
     * Store cart item in cart.
     *
     * @param Cartable|array $item
     * @param int $quantity
     * @return $this
     *
     * @throws CartException
     */
    public function storeItem(Cartable|array $item, int $quantity): static
    {
        if (is_array($item)) {
            $cartItemData = [
                'quantity' => $quantity,
                'itemable_id' => $item['itemable']->getKey(),
                'itemable_type' => get_class($item['itemable']),
                'price' => $item['itemable']->getPrice(),
                'sku' => $item['sku'] ?? $item['itemable']->getSku(),
                'name' => $item['itemable']->getName(),
                'base_price' => $item['itemable']->getPrice(),
                'total' => $item['itemable']->getPrice() * $quantity,
                'base_total' => $item['itemable']->getPrice() * $quantity,
                'weight' => $item['itemable']->getWeight() ?? 0,
                'total_weight' => ($item['itemable']->getWeight() ?? 0) * $quantity,
                'base_total_weight' => ($item['itemable']->getWeight() ?? 0) * $quantity,
            ];
            if ($item['itemable'] instanceof Cartable) {
                $this->items()->create($cartItemData);
            } else {
                throw new CartException(ErrorConst::ERROR_NOT_INSTANCE_CARTABLE);
            }
        } else {
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
        }

        return $this;
    }

}
