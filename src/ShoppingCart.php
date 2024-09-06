<?php

namespace MobileNowGroup\ShoppingCart;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use MobileNowGroup\ShoppingCart\Contracts\Buyable;
use MobileNowGroup\ShoppingCart\Contracts\InstanceIdentifier;
use MobileNowGroup\ShoppingCart\Exceptions\CartAlreadyStoredException;
use MobileNowGroup\ShoppingCart\Exceptions\InvalidRowIDException;
use MobileNowGroup\ShoppingCart\Exceptions\UnknownModelException;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;

class ShoppingCart
{
    use Macroable;

    const DEFAULT_INSTANCE = 'shopping';

    /**
     * Instance of the session manager.
     *
     * @var CartModel
     */
    private CartModel $cart;

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Defines the discount percentage.
     *
     * @var float
     */
    private $discount = 0;

    /**
     * Defines the tax rate.
     *
     * @var float
     */
    private $taxRate = 0;

    /**
     * Cart constructor.
     */
    public function __construct(CartModel $cart, Dispatcher $events)
    {
        $this->cart = $cart;
        $this->events = $events;
        $this->taxRate = config('shopping-cart.tax');

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param  string|null  $instance
     * @return $this
     */
    public function instance(?string $instance = null): self
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        /** @TODO */
        if ($instance instanceof InstanceIdentifier) {
            $this->discount = $instance->getInstanceGlobalDiscount();
            $instance = $instance->getInstanceIdentifier();
        }

        $this->cart->fill(['instance' => 'cart.' . $instance]);

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string|int
     */
    public function currentInstance(): string|int
    {
        return $this->cart->getKey();
    }

    /**
     * Add an item to the cart.
     *
     * @param  Model|string|array  $product
     * @param  string|null  $name
     * @param  int|float|null  $quantity
     * @param  float|null  $price
     * @param  float|int|null  $weight
     * @param  array|null  $options
     * @return \MobileNowGroup\ShoppingCart\CartItem|array
     */
    public function add(Model|string|array $product,
        ?string $name = null,
        int|float|null $quantity = 1,
        ?float $price = null,
        float|int|null $weight = null,
        ?array $options = []
    ): CartItem|array {
        if ($this->isMultiple($product)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $product);
        }

        $cartItem = $this->createCartItem($product, $name, $quantity, $price, $weight, $options);

        return $this->addCartItem($cartItem);
    }

    /**
     * Add an item to the cart.
     *
     * @param  \MobileNowGroup\ShoppingCart\CartItem  $item  Item to add to the Cart
     * @param  bool|null  $keepDiscount  Keep the discount rate of the Item
     * @param  bool|null  $keepTax  Keep the Tax rate of the Item
     * @param  bool|null  $dispatchEvent
     * @return \MobileNowGroup\ShoppingCart\CartItem The CartItem
     */
    public function addCartItem(
        CartItem $item,
        ?bool $keepDiscount = false,
        ?bool $keepTax = false,
        ?bool $dispatchEvent = true
    ): CartItem {
        if (! $keepDiscount) {
            $item->setDiscountRate($this->discount);
        }

        if (! $keepTax) {
            $item->setTaxRate($this->taxRate);
        }

        $content = $this->getContent();

        if ($content->has($item->rowId)) {
            $item->quantity += $content->get($item->rowId)->qty;
        }

        $content->put($item->rowId, $item);

        if ($dispatchEvent) {
            $this->events->dispatch('cart.adding', $item);
        }

        // @todo
        $this->cart->save();

        if ($dispatchEvent) {
            $this->events->dispatch('cart.added', $item);
        }

        return $item;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param  string  $rowId
     * @param  int|float|Buyable|array|null  $quantity
     * @return \MobileNowGroup\ShoppingCart\CartItem|bool
     */
    public function increment(string $rowId, int|float|Buyable|array|null $quantity): CartItem|bool
    {
        $cartItem = $this->get($rowId);

        if ($quantity instanceof Buyable) {
            $cartItem->updateFromBuyable($quantity);
        } elseif (is_array($quantity)) {
            $cartItem->updateFromArray($quantity);
        } else {
            $cartItem->quantity = $quantity;
        }

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $itemOldIndex = $content->keys()->search($rowId);

            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->quantity + $cartItem->quantity);
            }
        }

        if ($cartItem->quantity <= 0) {
            $this->remove($cartItem->rowId);

            return false;
        } else {
            if (isset($itemOldIndex)) {
                $content = $content->slice(0, $itemOldIndex)
                    ->merge([$cartItem->rowId => $cartItem])
                    ->merge($content->slice($itemOldIndex));
            } else {
                $content->put($cartItem->rowId, $cartItem);
            }
        }

        $this->events->dispatch('cart.updating', $cartItem);

        // @todo
        $this->cart->save();

        $this->events->dispatch('cart.updated', $cartItem);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param  string  $rowId
     * @return void
     */
    public function remove(string $rowId): void
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->events->dispatch('cart.removing', $cartItem);

        // @todo
        $this->cart->save();

        $this->events->dispatch('cart.removed', $cartItem);
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param  string  $rowId
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public function get(string $rowId): CartItem
    {
        $content = $this->getContent();

        if (! $content->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain id {$rowId}.");
        }

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->cart->forceDelete();
    }

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content(): Collection
    {
        // @todo
        return collect($this->cart);
    }

    /**
     * Get the total quantity of all CartItems in the cart.
     *
     * @return int|float
     */
    public function count(): int|float
    {
        return $this->getContent()->sum('quantity');
    }

    /**
     * Get the amount of CartItems in the Cart.
     * Keep in mind that this does NOT count quantity.
     *
     * @return int|float
     */
    public function countItems(): int|float
    {
        return $this->getContent()->count();
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @return float
     */
    public function totalFloat(): float
    {
        return $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->total;
        }, 0);
    }

    /**
     * Get the total price of the items in the cart as formatted string.
     *
     * @param  int|null  $decimals
     * @param  string|null  $decimalPoint
     * @param  string|null  $thousandSeparator
     * @return string
     */
    public function total(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->totalFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @return float
     */
    public function taxFloat(): float
    {
        return $this->getContent()->reduce(function ($tax, CartItem $cartItem) {
            return $tax + $cartItem->taxTotal;
        }, 0);
    }

    /**
     * Get the total tax of the items in the cart as formatted string.
     *
     * @param  int|null  $decimals
     * @param  string|null  $decimalPoint
     * @param  string|null  $thousandSeparator
     * @return string
     */
    public function tax(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->taxFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function subtotalFloat(): float
    {
        return $this->getContent()->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + $cartItem->subtotal;
        }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart as formatted string.
     *
     * @param  int|null  $decimals
     * @param  string|null  $decimalPoint
     * @param  string|null  $thousandSeparator
     * @return string
     */
    public function subtotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->subtotalFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the discount of the items in the cart.
     *
     * @return float
     */
    public function discountFloat(): float
    {
        return $this->getContent()->reduce(function ($discount, CartItem $cartItem) {
            return $discount + $cartItem->discountTotal;
        }, 0);
    }

    /**
     * Get the discount of the items in the cart as formatted string.
     *
     * @param  int|null  $decimals
     * @param  string|null  $decimalPoint
     * @param  string|null  $thousandSeparator
     * @return string
     */
    public function discount(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->discountFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the price of the items in the cart (not rounded).
     *
     * @return float
     */
    public function initialFloat(): float
    {
        return $this->getContent()->reduce(function ($initial, CartItem $cartItem) {
            return $initial + ($cartItem->quantity * $cartItem->price);
        }, 0);
    }

    /**
     * Get the price of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function initial(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->initialFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the price of the items in the cart (previously rounded).
     *
     * @return float
     */
    public function priceTotalFloat(): float
    {
        return $this->getContent()->reduce(function ($initial, CartItem $cartItem) {
            return $initial + $cartItem->priceTotal;
        }, 0);
    }

    /**
     * Get the price of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function priceTotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->priceTotalFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the total weight of the items in the cart.
     *
     * @return float
     */
    public function weightFloat(): float
    {
        return $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->quantity * $cartItem->weight);
        }, 0);
    }

    /**
     * Get the total weight of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function weight(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->weightFloat(), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search): Collection
    {
        return $this->getContent()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param  string  $rowId
     * @param  string|Buyable  $model
     * @return void
     */
    public function associate(string $rowId, string|Buyable $model): void
    {
        if (is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        // @todo
        $this->cart->save();
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param  string  $rowId
     * @param  int|float  $taxRate
     * @return void
     */
    public function setTax(string $rowId, int|float $taxRate): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        // @todo
        $this->cart->save();
    }

    /**
     * Set the global tax rate for the cart.
     * This will set the tax rate for all items.
     *
     * @param float $taxRate
     * @return void
     */
    public function setGlobalTax(float $taxRate): void
    {
        $this->taxRate = $taxRate;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setTaxRate($this->taxRate);
            });
        }
    }

    /**
     * Set the discount rate for the cart item with the given rowId.
     *
     * @param  string  $rowId
     * @param  int|float  $discount
     * @return void
     */
    public function setDiscount(string $rowId, int|float $discount): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->setDiscountRate($discount);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        // @todo
        $this->cart->save();
    }

    /**
     * Set the global discount percentage for the cart.
     * This will set the discount for all cart items.
     *
     * @param  float  $discount
     * @return void
     */
    public function setGlobalDiscount(float $discount): void
    {
        $this->discount = $discount;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setDiscountRate($this->discount);
            });
        }
    }

    /**
     * @todo
     * Store the current instance of the cart.
     *
     * @param  mixed  $identifier
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();

        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $instance = $this->currentInstance();

        if ($this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $serializedContent = serialize($content);

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $instance,
            'content' => $serializedContent,
            'created_at' => $this->createdAt ?: Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->events->dispatch('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param  InstanceIdentifier|string  $identifier
     * @return void
     */
    public function restore(string|InstanceIdentifier $identifier)
    {
        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $currentInstance = $this->currentInstance();

        if (! $this->storedCartInstanceWithIdentifierExists($currentInstance, $identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where(['identifier' => $identifier, 'instance' => $currentInstance])->first();

        if ($this->getConnection()->getDriverName() === 'pgsql') {
            $storedContent = unserialize(base64_decode(data_get($stored, 'content')));
        } else {
            $storedContent = unserialize(data_get($stored, 'content'));
        }

        $this->instance(data_get($stored, 'instance'));

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.restored');

        // @todo
        $this->cart->save();

        $this->instance($currentInstance);

        $this->getConnection()->table($this->getTableName())->where(['identifier' => $identifier, 'instance' => $currentInstance])->delete();
    }

    /**
     * Erase the cart with the given identifier.
     *
     * @param  mixed  $identifier
     * @return void
     */
    public function erase(string|InstanceIdentifier $identifier): void
    {
        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $instance = $this->currentInstance();

        if (! $this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            return;
        }

        $this->getConnection()->table($this->getTableName())->where(['identifier' => $identifier, 'instance' => $instance])->delete();

        $this->events->dispatch('cart.erased');
    }

    /**
     * Merges the contents of another cart into this cart.
     *
     * @param  mixed  $identifier  Identifier of the Cart to merge with.
     * @param  bool  $keepDiscount  Keep the discount of the CartItems.
     * @param  bool  $keepTax  Keep the tax of the CartItems.
     * @param  bool  $dispatchAdd  Flag to dispatch the add events.
     * @return bool
     */
    public function merge($identifier, $keepDiscount = false, $keepTax = false, $dispatchAdd = true, $instance = self::DEFAULT_INSTANCE)
    {
        if (! $this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            return false;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where(['identifier' => $identifier, 'instance' => $instance])->first();

        if ($this->getConnection()->getDriverName() === 'pgsql') {
            $storedContent = unserialize(base64_decode($stored->content));
        } else {
            $storedContent = unserialize($stored->content);
        }

        foreach ($storedContent as $cartItem) {
            $this->addCartItem($cartItem, $keepDiscount, $keepTax, $dispatchAdd);
        }

        $this->events->dispatch('cart.merged');

        return true;
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param  string  $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        switch ($attribute) {
            case 'total':
                return $this->total();
            case 'tax':
                return $this->tax();
            case 'subtotal':
                return $this->subtotal();
            default:
                return;
        }
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getContent()
    {
        if ($this->session->has($this->instance)) {
            return $this->session->get($this->instance);
        }

        return new Collection();
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param  Model|array|string  $product
     * @param  string  $name
     * @param  int|float|null $quantity
     * @param  float|null  $price
     * @param  float|int|null  $weight
     * @param  array|null  $options
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    protected function createCartItem(
        Model|string|array $product,
        string $name,
        int|float|null $quantity = 1,
        ?float $price = null,
        float|int|null $weight = null,
        ?array $options = []
    ): CartItem {
        if ($product instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($product, $quantity ?: []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($product);
        } elseif (is_array($product)) {
            $cartItem = CartItem::fromArray($product);
            $cartItem->setQuantity($product['quantity']);
        } else {
            $cartItem = CartItem::fromAttributes($product, $name, $price, $weight, $options);
            $cartItem->setQuantity($quantity);
        }

        $cartItem->setInstance($this->currentInstance());

        return $cartItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyable.
     *
     * @param  mixed  $item
     * @return bool
     */
    private function isMultiple(mixed $item): bool
    {
        if (! is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * @todo
     *
     * @return bool
     */
    private function storedCartInstanceWithIdentifierExists($instance, $identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where(['identifier' => $identifier, 'instance' => $instance])->exists();
    }

    /**
     * @todo
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    private function getConnection()
    {
        return app(DatabaseManager::class)->connection($this->getConnectionName());
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName()
    {
        return config('shopping-cart.database.table', 'shopping_carts');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }

    /**
     * Get the Formatted number.
     *
     *
     * @param float $value
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    private function numberFormat(float $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator): string
    {
        if (is_null($decimals)) {
            $decimals = config('shopping-cart.format.decimals', 2);
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = config('shopping-cart.format.decimal_point', '.');
        }

        if (is_null($thousandSeparator)) {
            $thousandSeparator = config('cart.format.thousand_separator', ',');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }
}
