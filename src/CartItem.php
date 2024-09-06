<?php

namespace MobileNowGroup\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use MobileNowGroup\ShoppingCart\Calculation\DefaultCalculator;
use MobileNowGroup\ShoppingCart\Contracts\Buyable;
use MobileNowGroup\ShoppingCart\Contracts\Calculator;
use MobileNowGroup\ShoppingCart\Exceptions\InvalidCalculatorException;
use ReflectionClass;

/**
 * @property-read mixed discount
 * @property-read float discountTotal
 * @property-read float priceTarget
 * @property-read float priceNet
 * @property-read float priceTotal
 * @property-read float subtotal
 * @property-read float taxTotal
 * @property-read float tax
 * @property-read float total
 * @property-read float priceTax
 */
class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public string $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public string|int $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public int|float $quantity;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public string $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public float $price;

    /**
     * The weight of the product.
     *
     * @var float|int
     */
    public float|int $weight;

    /**
     * The options for this cart item.
     *
     * @var CartItemOptions|array
     */
    public CartItemOptions|array $options;

    /**
     * The tax rate for the cart item.
     *
     * @var int|float
     */
    public int|float $taxRate = 0;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private ?string $associatedModel = null;

    /**
     * The discount rate for the cart item.
     *
     * @var float|int
     */
    private float|int $discountRate = 0;

    /**
     * The cart instance of the cart item.
     *
     * @var null|string
     */
    public ?string $instance = null;

    /**
     * CartItem constructor.
     *
     * @param string $id
     * @param string $name
     * @param float|int $price
     * @param int|float $weight
     * @param array $options
     */
    public function __construct(string $id, string $name, float|int $price, int|float $weight = 0, array $options = [])
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if ($price < 0) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }
        if ($weight < 0) {
            throw new \InvalidArgumentException('Please supply a valid weight.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = floatval($price);
        $this->weight = floatval($weight);
        $this->options = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Returns the formatted weight.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function weight(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->weight, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function price(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->price, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price with discount applied.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function priceTarget(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->priceTarget, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price with TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function priceTax(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->priceTax, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function subtotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->subtotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function total(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->total, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function tax(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->tax, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function taxTotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->taxTotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted discount.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function discount(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->discount, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total discount for this cart item.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function discountTotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->discountTotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total price for this cart item.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function priceTotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): string
    {
        return $this->numberFormat($this->priceTotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param  int|float  $quantity
     * @return void
     */
    public function setQuantity(int|float $quantity): void
    {
        if (empty($quantity) || ! is_numeric($quantity)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        $this->quantity = $quantity;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param \MobileNowGroup\ShoppingCart\Contracts\Buyable $item
     * @return void
     */
    public function updateFromBuyable(Buyable $item): void
    {
        $this->id = $item->getBuyableIdentifier($this->options);
        $this->name = $item->getBuyableDescription($this->options);
        $this->price = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @return void
     */
    public function updateFromArray(array $attributes): void
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->quantity = Arr::get($attributes, 'qty', $this->quantity);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->weight = Arr::get($attributes, 'weight', $this->weight);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param  mixed  $model
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public function associate(string|Buyable $model): self
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param  int|float  $taxRate
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public function setTaxRate(int|float $taxRate): self
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Set the discount rate.
     *
     * @param  int|float  $discountRate
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public function setDiscountRate(int|float $discountRate): self
    {
        $this->discountRate = $discountRate;

        return $this;
    }

    /**
     * Set cart instance.
     *
     * @param  null|string  $instance
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public function setInstance(?string $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param  string  $attribute
     * @return mixed
     */
    public function __get(string $attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }
        $decimals = config('shopping-cart.format.decimals', 2);

        switch ($attribute) {
            case 'model':
                if (isset($this->associatedModel)) {
                    return with(new $this->associatedModel())->find($this->id);
                }
                // no break
            case 'modelFQCN':
                if (isset($this->associatedModel)) {
                    return $this->associatedModel;
                }
                // no break
            case 'weightTotal':
                return round($this->weight * $this->quantity, $decimals);
        }

        $class = new ReflectionClass(config('shopping-cart.calculator', DefaultCalculator::class));
        if (! $class->implementsInterface(Calculator::class)) {
            throw new InvalidCalculatorException('The configured Calculator seems to be invalid. Calculators have to implement the Calculator Contract.');
        }

        return call_user_func($class->getName() . '::getAttribute', $attribute, $this);
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \MobileNowGroup\ShoppingCart\Contracts\Buyable $item
     * @param array $options
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = []): self
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), $item->getBuyablePrice($options), $item->getBuyableWeight($options), $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public static function fromArray(array $attributes): self
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $attributes['weight'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param string $id
     * @param string $name
     * @param int|float $price
     * @param int|float $weight
     * @param array $options
     * @return \MobileNowGroup\ShoppingCart\CartItem
     */
    public static function fromAttributes(string $id, string $name, int|float $price, int|float $weight, array $options = []): self
    {
        return new self($id, $name, $price, $weight, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param  string  $id
     * @return string
     */
    protected function generateRowId(string $id, array $options): string
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'rowId' => $this->rowId,
            'id' => $this->id,
            'name' => $this->name,
            'qty' => $this->quantity,
            'price' => $this->price,
            'weight' => $this->weight,
            'options' => is_object($this->options)
                ? $this->options->toArray()
                : $this->options,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'subtotal' => $this->subtotal,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the formatted number.
     *
     * @param float $value
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    private function numberFormat(float $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator)
    {
        if (is_null($decimals)) {
            $decimals = config('cart.format.decimals', 2);
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = config('cart.format.decimal_point', '.');
        }

        if (is_null($thousandSeparator)) {
            $thousandSeparator = config('cart.format.thousand_separator', ',');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Getter for the raw internal discount rate.
     * Should be used in calculators.
     *
     * @return float|int
     */
    public function getDiscountRate(): float|int
    {
        return $this->discountRate;
    }
}
