<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Fayne Shopping Cart

[简体中文](README.md) | English

A Laravel package for managing shopping cart state and checkout snapshots. It supports cart line operations, item selection, buy now, coupon extension points, checkout snapshots, and configurable storage adapters. Requires PHP 8.2+ and Laravel 10+.

## Features

| Capability | Description |
| --- | --- |
| Cart operations | Retrieve, add, update quantities, remove items, empty a cart, and select items for checkout |
| Buy now | Isolate a buy-now item in a temporary checkout cart and restore the regular cart when returning |
| Totals | Calculate line and cart totals with cent-based rounding and discount snapshots |
| Coupon extension | Let the host validate coupons and persist per-line discounts, with revalidation before checkout |
| Checkout safeguards | Serialize cart writes in transactions and provide host callbacks for stock validation/reservation and checkout history |
| Laravel integration | Service provider, Facade, API routes, events, configuration, migrations, and Artisan operations commands |
| Storage drivers | `database`, `cache`, `redis`, and `layered` configuration names; all built-in drivers currently use the database as the source of truth |

This package manages cart state and checkout snapshots. It does not create host orders or implement payment idempotency, payment state transitions, stock release, tax/shipping, or fulfillment. The host application must provide those integrations.

## Installation

```bash
composer require fayne/shopping-cart
php artisan vendor:publish --provider="Fayne\ShoppingCart\ShoppingCartServiceProvider" --tag=config
php artisan migrate
```

Laravel discovers the service provider and `ShoppingCart` Facade through Composer. Migrations are loaded by the provider. To manage migration files yourself, publish them explicitly:

```bash
php artisan vendor:publish --provider="Fayne\ShoppingCart\ShoppingCartServiceProvider" --tag=migrations
```

For a private Composer repository, configure the repository in the host project before running `composer require fayne/shopping-cart`. Review the production integration requirements below before deployment.

## Configuration

The published configuration is `config/shopping-cart.php`. At minimum, production must configure checkout and coupon validation:

```php
// config/shopping-cart.php
return [
    'checkout' => [
        'validator' => App\Shopping\ValidateCartStock::class,
        'currency' => 'CNY',
    ],
    'coupon_validator' => App\Shopping\ValidateCartCoupon::class,
];
```

`checkout.validator` is an invokable class receiving `(Cartable $sku, int $quantity, ShoppingCart $cart)` and returning a boolean. It must validate and lock or reserve inventory inside the checkout transaction. Checkout is rejected when no validator is configured.

`coupon_validator` receives `(string $code, ShoppingCart $cart, Fayne\ShoppingCart\ShoppingCart $service)` and returns a boolean. It must set `discount_amount` and `base_discount_amount` for eligible lines and clear discounts that are no longer valid. Coupons cannot be applied without this validator.

The host must provide authenticated users so `auth()->id()` returns the customer ID. The default customer foreign key targets an integer `users.id`; adapt the column and foreign key before installation if using ULIDs/UUIDs. Configure the product/SKU model, order class, and order-number generator in `config/shopping-cart.php` for your host integration.

## Cartable product contract

A purchasable model must implement `Fayne\ShoppingCart\Contracts\Cartable`:

```php
use Fayne\ShoppingCart\Contracts\Cartable;
use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model implements Cartable
{
    public function getPrice(): float { /* current unit price */ }
    public function getSku(): string { /* SKU code */ }
    public function getName(): string { /* display name */ }
    public function getWeight(): float|int|null { /* weight or null */ }
    public function canBePurchased(): bool { /* current sale eligibility */ }
}
```

The model must also be an Eloquent model with a primary key and morph class. Stock checks belong in the checkout validator. Return the current effective price from `getPrice()` and `false` from `canBePurchased()` when the product is unavailable.

## Usage

Use the auto-discovered `ShoppingCart` Facade:

```php
use Fayne\ShoppingCart\Facades\ShoppingCart;

$cart = ShoppingCart::getCart();
ShoppingCart::addProduct($sku, quantity: 2);
ShoppingCart::updateItems($itemId, quantity: 3);
ShoppingCart::setCartItemSelection($itemId, selected: true);
ShoppingCart::collectTotals();
ShoppingCart::setCouponCode('WELCOME');
ShoppingCart::removeCouponCode();
ShoppingCart::removeItem($itemId);
ShoppingCart::emptyCart();
```

Use `POST /modules/shopping-cart/api/cart/instant-purchase` to buy now and `DELETE` on the same path to return to the regular cart. These paths assume the default configuration.

The service wraps its own mutations in database transactions. The host should create orders within a clear transactional/idempotency boundary and complete cart state transitions after successful order creation. Avoid direct model writes that bypass ownership checks, locking, or totals calculation.

## Events and API routes

The provider loads package routes and registers cart operation and totals events by default. Configure `routes.enabled`, `routes.prefix`, and `events.enabled` as needed. Before release, inspect the registered routes and authentication middleware to ensure customers can only access their own carts.

Listen to `Fayne\ShoppingCart\Events\CheckoutValidating` for synchronous checkout validation or stock reservation on the same database connection. It runs inside a transaction, and transaction retries may invoke listeners more than once. Keep listeners idempotent. Do not queue stock validation or perform uncompensated external side effects inside the transaction.

## Production requirements

- Back up the database and rehearse the `2026_11_02_000001_preserve_checkout_history` migration against an anonymized data copy. It adds discount history and changes product/SKU foreign keys to RESTRICT. Archive products referenced by checkout history instead of hard-deleting them.
- The migration does not reconstruct historical discounts. Reconcile pending checkout history and order header/line amounts; do not recalculate paid transactions.
- All built-in storage configurations currently use the database as the source of truth. `cache`, `redis`, and `layered` names no longer imply serialized model snapshot caching.
- Prices are rounded per line to cents. Tax and shipping are currently zero. Order adapters must consume line `discount_amount` and discounted `total_amount`.
- The host owns inventory integration, order creation, payment, idempotency, and compensation for external inventory systems. SQLite tests do not prove MySQL/PostgreSQL row-lock behavior.
- `cart:migrate-data` verifies built-in driver reads and does not copy carts. Run performance benchmarks only against disposable test data.

Production release requires target database concurrency tests, rehearsal with existing data, and host inventory/payment integration. See the [acceptance evidence](docs/production-acceptance-2026-11-03.md).

## Development

```bash
composer test
composer analyse
composer format
```

Do not commit this package's `composer.lock`. Real MySQL/Redis acceptance tests require explicitly configured disposable service ports; see the [acceptance evidence](docs/production-acceptance-2026-11-03.md).

## License

Proprietary License. See [LICENSE.md](LICENSE.md).

[Production release checklist (Chinese)](docs/production-release-gates.md)
