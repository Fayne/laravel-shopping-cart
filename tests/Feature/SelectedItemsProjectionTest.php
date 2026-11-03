<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Fayne\Commerce\Catalog\Models\ProductVariant;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SelectedItemsProjectionTest extends IntegrationTestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('shopping-cart.models.users.enforce_cart_foreign_key', false);
    }

    public function test_configured_sku_must_implement_the_cartable_contract(): void
    {
        config(['shopping-cart.models.skus.model' => \Illuminate\Foundation\Auth\User::class]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Configure an Eloquent SKU model implementing Cartable.');
        \Fayne\ShoppingCart\Support\CartSku::model();
    }

    public function test_quote_projection_contains_only_selected_identifiers_and_quantity(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $sku = QuoteSku::create(['price' => 0]);
        $cart = $service->addProduct($sku, 2);
        $line = $cart->items->sole();
        $other = $service->addProduct(QuoteSku::create(['price' => 3]), 1);
        $service->setCartItemSelection($other->items->firstWhere('id', '!=', $line->id)->id, false);

        $this->assertSame([[
            'cart_item_id' => $line->id,
            'sku_id' => $sku->id,
            'quantity' => 2,
            'selected' => true,
        ]], $service->selectedItemsForQuote());
    }

    public function test_add_product_rejects_integer_overflow(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $this->expectException(\Fayne\ShoppingCart\Exceptions\CartClientException::class);
        $service->addProduct(QuoteSku::create(['price' => 9]), 2);
    }
    public function test_add_product_rejects_negative_minor_units(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $this->expectException(HttpException::class);
        $service->addProduct(QuoteSku::create(['price' => 8]), 1);
    }

    public function test_quote_projection_preserves_integer_sku_keys(): void
    {
        \Illuminate\Support\Facades\Schema::create('integer_skus', function ($table): void {
            $table->id();
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
        config(['shopping-cart.models.skus.model' => IntegerQuoteSku::class]);
        $sku = IntegerQuoteSku::create(['price' => 2]);
        $service = app(ShoppingCart::class);
        $service->addProduct($sku, 1);

        $this->assertSame($sku->id, $service->selectedItemsForQuote()[0]['sku_id']);
    }

    public function test_commerce_requotes_selected_lines_from_current_catalog_prices(): void
    {
        $packages = dirname(__DIR__, 3);
        if (!is_file(dirname($packages) . '/vendor/autoload.php')
            || !is_dir($packages . '/laravel-filament-commerce/src')
            || !is_dir($packages . '/laravel-filament-promotion/src')
            || !class_exists(\Fayne\CMS\Support\TenantContext::class)) {
            $this->markTestSkipped('Commerce, Promotion and CMS are required for cross-package acceptance.');
        }

        $loader = require dirname($packages) . '/vendor/autoload.php';
        $loader->addPsr4('Fayne\\Commerce\\', $packages . '/laravel-filament-commerce/src');
        $loader->addPsr4('Fayne\\Promotion\\', $packages . '/laravel-filament-promotion/src');

        foreach ([
            '/laravel-filament-commerce/database/migrations/2026_11_02_000001_create_commerce_catalog_tables.php',
            '/laravel-filament-promotion/database/migrations/2026_11_02_000001_create_promotions_table.php',
            '/laravel-filament-commerce/database/migrations/2026_11_02_000003_create_commerce_order_tables.php',
            '/laravel-filament-commerce/database/migrations/2026_11_02_000010_create_commerce_customers.php',
        ] as $migration) {
            (require $packages . $migration)->up();
        }

        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'filament-cms.tenancy.enabled' => false,
            'shopping-cart.models.skus.model' => ProductVariant::class,
        ]);
        config([
            'auth.guards.customer' => ['driver' => 'session', 'provider' => 'commerce-customers'],
            'auth.providers.commerce-customers' => [
                'driver' => 'eloquent', 'model' => \Fayne\Commerce\Customers\Models\Customer::class,
            ],
        ]);
        \Illuminate\Support\Facades\DB::table('commerce_customers')->insert([
            'id' => 42, 'tenant_id' => 0, 'name' => 'Test Buyer', 'password' => 'unused',
        ]);
        auth()->guard('customer')->setUser(\Fayne\Commerce\Customers\Models\Customer::findOrFail(42));
        app()->instance(\Fayne\ShoppingCart\Contracts\CartContextResolver::class, new class implements \Fayne\ShoppingCart\Contracts\CartContextResolver
        {
            public function tenantId(): int|string
            {
                return app(\Fayne\CMS\Support\TenantContext::class)->requireId();
            }

            public function customerId(): int|string|null
            {
                return auth()->guard('customer')->id();
            }

            public function sessionId(): ?string
            {
                return null;
            }
        });
        \Illuminate\Support\Facades\DB::table('commerce_products')->insert([
            'id' => 1, 'tenant_id' => 0, 'type' => 'digital', 'status' => 'active',
        ]);
        \Illuminate\Support\Facades\DB::table('commerce_product_translations')->insert([
            'tenant_id' => 0, 'product_id' => 1, 'locale' => 'en', 'name' => 'Digital SKU',
        ]);

        foreach ([1, 2] as $id) {
            \Illuminate\Support\Facades\DB::table('commerce_product_variants')->insert([
                'id' => $id, 'tenant_id' => 0, 'product_id' => 1, 'sku' => "sku-{$id}", 'price_amount' => 200,
            ]);
        }

        app()->bind(\Fayne\ShoppingCart\Contracts\CartSkuQuery::class, \Fayne\Commerce\Cart\CommerceSkuQuery::class);
        $service = app(ShoppingCart::class);
        $sku = ProductVariant::findOrFail(1);
        $tenant = new \Fayne\CMS\Models\Tenant;
        $tenant->id = 2;
        config(['filament-cms.tenancy.enabled' => true]);
        try {
            app(\Fayne\CMS\Support\TenantContext::class)->run($tenant, fn () => $service->addProduct($sku, 2));
            $this->fail('Expected a SKU from another tenant to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        } finally {
            config(['filament-cms.tenancy.enabled' => false]);
        }
        $this->assertDatabaseCount('shopping_cart_items', 0);

        app()->bind(\Fayne\ShoppingCart\Contracts\CartCurrencyResolver::class, \Fayne\Commerce\Tenancy\Actions\TenantCurrency::class);
        \Illuminate\Support\Facades\DB::table('commerce_tenant_settings')->insert(['tenant_id' => 0, 'currency' => 'USD']);
        try {
            $service->addProduct($sku, 2);
            $this->fail('Expected a SKU in a different currency to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        } finally {
            \Illuminate\Support\Facades\DB::table('commerce_tenant_settings')->where('tenant_id', 0)->update(['currency' => 'CNY']);
        }

        $sku->price_amount = 1;
        $initialCart = $service->addProduct($sku, 2);
        $this->assertEquals(2, $initialCart->items->sole()->price);
        $cart = $service->addProduct(ProductVariant::findOrFail(2), 1);
        $service->setCartItemSelection($cart->items->firstWhere('itemable_id', 2)->id, false);
        \Illuminate\Support\Facades\DB::table('commerce_product_variants')->where('id', 1)->update(['price_amount' => 350]);
        $selected = $service->selectedItemsForQuote();
        $lines = array_map(static fn (array $item): array => [
            'variant_id' => $item['sku_id'], 'quantity' => $item['quantity'],
        ], $service->selectedItemsForQuote());

        $quote = app(\Fayne\Commerce\Pricing\Actions\QuoteProducts::class)->execute($lines, 0, customerId: 42, locale: 'en');

        $this->assertSame(700, $quote->subtotal);
        $this->assertSame([1], array_column($quote->lines, 'variant_id'));
        $this->assertSame([2], array_column($quote->lines, 'quantity'));

        $placeAndConsume = function () use ($service, $cart, $selected, $quote) {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($service, $cart, $selected, $quote) {
                $order = app(\Fayne\Commerce\Orders\Actions\PlaceOrder::class)->execute(
                    $quote, ['name' => 'Test Buyer', 'email' => 'buyer@example.test'], null,
                    'cart-integration', \Illuminate\Support\Carbon::now()->addMinutes(30),
                );
                $service->consumeOrderItems($cart->id, $order->id, $selected);

                return $order;
            });
        };

        $service->updateItems($selected[0]['cart_item_id'], 3);
        try {
            $placeAndConsume();
            $this->fail('Expected stale cart to roll back the Commerce order.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('commerce_order_lines', 0);
        $this->assertDatabaseCount('commerce_outbox_messages', 0);
        $this->assertDatabaseCount('shopping_cart_order_consumptions', 0);
        $this->assertCount(2, $service->getCart()->items);

        $service->updateItems($selected[0]['cart_item_id'], 2);
        $order = $placeAndConsume();
        $this->assertSame(700, $order->payable_amount);
        $this->assertSame(42, (int) $order->customer_id);
        $this->assertSame(1, $order->lines->sole()->variant_id);
        $this->assertSame(2, $order->lines->sole()->quantity);
        $this->assertSame('2', (string) $service->getCart()->items->sole()->itemable_id);
        $this->assertDatabaseCount('shopping_cart_order_consumptions', 1);
    }

    public function test_order_consumption_rolls_back_and_retry_preserves_unselected_items(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $cart = $service->addProduct(QuoteSku::create(['price' => 2]), 2);
        $selected = $cart->items->sole()->id;
        $cart = $service->addProduct(QuoteSku::create(['price' => 3]), 1);
        $unselected = $cart->items->firstWhere('id', '!=', $selected)->id;
        $service->setCartItemSelection($unselected, false);
        $lines = $service->selectedItemsForQuote();

        \Illuminate\Support\Facades\DB::beginTransaction();
        $service->consumeOrderItems($cart->id, 'order-1', $lines);
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertSame($lines, $service->selectedItemsForQuote());

        \Illuminate\Support\Facades\DB::transaction(function () use ($service, $cart, $lines): void {
            $service->consumeOrderItems($cart->id, 'order-1', $lines);
            $service->consumeOrderItems($cart->id, 'order-1', $lines);
        });

        $this->assertSame($unselected, $service->getCart()->items->sole()->id);
    }

    public function test_stale_quote_cannot_consume_changed_quantity(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $cart = $service->addProduct(QuoteSku::create(['price' => 2]), 1);
        $lines = $service->selectedItemsForQuote();
        $service->updateItems($cart->items->sole()->id, 2);

        try {
            \Illuminate\Support\Facades\DB::transaction(
                fn () => $service->consumeOrderItems($cart->id, 'stale', $lines)
            );
            $this->fail('Expected stale quantity to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(2, $service->selectedItemsForQuote()[0]['quantity']);
        $this->assertDatabaseCount('shopping_cart_order_consumptions', 0);
    }

    public function test_consuming_temporary_cart_restores_regular_cart_once(): void
    {
        config(['shopping-cart.models.skus.model' => QuoteSku::class]);
        $service = app(ShoppingCart::class);
        $sku = QuoteSku::create(['price' => 2]);
        $regular = $service->addProduct($sku, 1);
        request()->merge(['is_buy_now' => true]);
        $service->deactivateCurrentCartIfBuyNowIsActive();
        $temporary = $service->addProduct($sku, 2, true);
        $lines = $service->selectedItemsForQuote();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            \Illuminate\Support\Facades\DB::transaction(
                fn () => $service->consumeOrderItems($temporary->id, 'instant', $lines)
            );
            $this->assertSame($regular->id, $service->getCart()?->id);
            $this->assertSame(1, $service->selectedItemsForQuote()[0]['quantity']);
        }
    }

}

class QuoteSku extends Product
{
    public function getPriceInMinorUnits(): int
    {
        return match ((float) $this->price) {
            8.0 => -1,
            9.0 => PHP_INT_MAX,
            default => parent::getPriceInMinorUnits(),
        };
    }
}

class IntegerQuoteSku extends QuoteSku
{
    protected $table = 'integer_skus';

    public function uniqueIds(): array
    {
        return [];
    }
}
