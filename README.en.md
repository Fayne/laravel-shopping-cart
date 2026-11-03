<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Fayne Shopping Cart

[简体中文](README.md) | English

A Laravel package for managing shopping cart state and checkout snapshots. It supports cart line operations, item selection, buy now, coupon extension points, checkout snapshots, and configurable storage adapters. Requires PHP 8.2+ and Laravel 12 or 13 (Laravel 13 requires PHP 8.3+).

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

Laravel discovers the service provider and `ShoppingCart` Facade through Composer. Migrations are loaded by the provider. To manage migration files yourself, publish them explicitly: The bundled migrations are a fresh-install baseline. Databases with earlier package migrations require a separate upgrade plan.

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
    public function getPriceInMinorUnits(): int { /* current unit amount in minor units */ }
    public function getCurrency(): string { /* currency code */ }
    public function getSku(): string { /* SKU code */ }
    public function getName(): string { /* display name */ }
    public function getWeight(): float|int|null { /* weight or null */ }
    public function canBePurchased(): bool { /* current sale eligibility */ }
}
```

The model must also be an Eloquent model with a primary key and morph class. Stock checks belong in the checkout validator. Return the current effective price from `getPriceInMinorUnits()` and `false` from `canBePurchased()` when the product is unavailable.

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

- The initial schema includes line discount snapshots and RESTRICT foreign keys for products and SKUs. Archive catalog entries referenced by checkout history.
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

## Tenant integration preview

Bind `Fayne\ShoppingCart\Contracts\CartContextResolver` to provide tenant and authenticated customer IDs from trusted host context. Enabling `shopping-cart.tenancy.enabled` rejects access without a resolver. Trusted customers and guests are locked by tenant and identity. Existing cart foreign keys may still reference the host user table and must be adapted before using a separate customer table. Host services can use guest carts by resolving a server-side session identity. Carts are isolated by tenant and session and are never merged automatically on login. Set `shopping-cart.routes.guests=true` to enable cart routes with Laravel web sessions and CSRF protection. The resolver must derive identity from the server-side session. Built-in checkout still requires authentication. Commerce order handoff is pending.

### Backfill historical tenant ownership

Run the migrations on an anonymized backup first. Provide authoritative tenant mappings keyed by cart and Checkout ULID. In a transaction, update each parent row by primary key where `tenant_id IS NULL`. Verify affected row counts and check for duplicate active carts per tenant and customer before committing. Customer IDs alone cannot establish tenant ownership. Keep unresolved rows unassigned so tenant mode excludes them. Reconcile the data and rehearse recovery before switching traffic.

An offline upgrade script can call `Fayne\ShoppingCart\Storage\TenantOwnershipBackfill::apply($carts, $checkouts)` with tenant mappings keyed by historical cart and Checkout ULID. Unknown or assigned rows and duplicate active carts roll back the batch. Pause writes during cutover and use the pre-migration backup to restore the original ownership data.

For an independent customer table, set `CART_ENFORCE_USER_FOREIGN_KEY=false` before running the initial cart migration. The host owns cart cleanup on customer deletion. The customer key remains an integer.

### Commerce quote handoff

Call `ShoppingCart::selectedItemsForQuote()` to project selected lines from the current cart. SKU models must implement `Fayne\ShoppingCart\Contracts\Cartable`. Each line contains `cart_item_id`, `sku_id`, `quantity`, and `selected`. Commerce uses the SKU and quantity to quote again and validate tenant ownership, pricing, currency, stock, and promotions.

After creating the order inside a transaction on the same database connection, call `ShoppingCart::consumeOrderItems($cartId, $orderId, $quotedItems)`. It verifies selection, SKU, and quantity. If the cart changed, roll back the order and quote again. Consumption can be retried with the same order and quote.


Commerce currently accepts integer `variant_id` values in `QuoteProducts::execute()`. Map the projection as shown below. The host supplies shipping and the customer ID.

```php
$selected = ShoppingCart::selectedItemsForQuote();
$requestedLines = array_map(static fn (array $line): array => [
    'variant_id' => $line['sku_id'],
    'quantity' => $line['quantity'],
], $selected);
$quote = app(\Fayne\Commerce\Pricing\Actions\QuoteProducts::class)
    ->execute($requestedLines, $shippingAmount, customerId: $customerId);
```

Set the trusted tenant and customer context in the host and call `consumeOrderItems()` inside the order transaction. Commerce uses its own order entry point.

The host can wrap selection, Commerce quoting and order creation, and cart consumption in `ShoppingCart::transaction()`. This acquires the cart ownership lock before accessing orders and inventory. Use the same connection for the entire flow and let conflicts abort the transaction.

For tenant pricing, bind `CartCurrencyResolver` to the host currency service and bind `CartSkuQuery` to constrain SKU queries to the trusted tenant. Cart display columns currently assume currencies with two decimal places. Commerce order amounts remain integers.

The default SKU model is `Fayne\ShoppingCart\Models\CartSku`, backed by `shopping_cart_skus`. Override `shopping-cart.models.skus.model` with your own Eloquent model implementing `Cartable` if needed. The built-in API looks up SKU codes in the `sku` column. Tenant mode also requires a `CartSkuQuery` binding.
