<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Fayne\ShoppingCart\Models\ShoppingCart as Cart;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Storage\DatabaseCartStorage;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Http\Controllers\CheckoutController;
use Fayne\ShoppingCart\Models\Checkout;
use Fayne\ShoppingCart\Storage\CacheCartStorage;
use Fayne\ShoppingCart\Storage\RedisCartStorage;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductionTest extends IntegrationTestCase
{
    public function test_store_api_uses_configured_sku_model_and_returns_current_totals(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('sku')->nullable());
        Schema::table('product_skus', fn (Blueprint $table) => $table->string('sku')->nullable());
        $product = Product::create(['price'=>12.34,'sku'=>'test-sku']);
        \Illuminate\Support\Facades\DB::table('product_skus')->insert(['id'=>$product->id,'sku'=>'test-sku']);
        config(['shopping-cart.models.skus.model'=>Product::class]);
        $this->postJson('/modules/shopping-cart/api/cart',['code'=>'test-sku','quantity'=>2])
            ->assertOk()->assertJsonPath('data.items.0.quantity',2)->assertJsonPath('data.grand_total',24.68);
    }

    public function test_empty_cart_remains_visible(): void
    {
        $storage = new DatabaseCartStorage;
        $cart = $storage->createCart(['customer_id' => 1]);
        $this->assertSame($cart->id, $storage->getCart('1')?->id);
    }
    public function test_service_rejects_foreign_item_selection(): void
    {
        $storage = new DatabaseCartStorage;
        $own = $storage->createCart(['customer_id' => 1]);
        $ownProduct = Product::create(['price' => 10]);
        $storage->addCartItem($own->id, ['itemable_id' => $ownProduct->id, 'itemable_type' => Product::class, 'quantity' => 1]);
        DB::table('users')->insert(['id' => 2, 'name' => 'Two']);
        $other = $storage->createCart(['customer_id' => 2]);
        $product = Product::create(['price' => 12.34]);
        $item = $storage->addCartItem($other->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 1]);
        try { (new ShoppingCart($storage))->setCartItemSelection($item->id, true); } catch (\Throwable $e) {}
        $this->assertFalse($item->fresh()->selected);
    }
    public function test_cache_reads_changes_and_distinct_statistics(): void
    {
        config(['cache.default' => 'array']);
        $storage = new CacheCartStorage(new DatabaseCartStorage, 'test', 60, 'array');
        $cart = $storage->createCart(['customer_id' => 1]);
        $product = Product::create(['price' => 12.34]);
        $storage->getCart('1');
        $storage->addCartItem($cart->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 3]);
        $this->assertCount(1, $storage->getCart('1')->items);
        $this->assertSame(1, $storage->getItemsCount('1'));
        $this->assertSame(3, $storage->getItemsQty('1'));
    }

    public function test_mutations_recalculate_totals_and_reject_negative_quantity(): void
    {
        $service = new ShoppingCart(new DatabaseCartStorage);
        $product = Product::create(['price' => 12.34]);
        $cart = $service->addProduct($product, 2);
        $this->assertEquals(24.68, $cart->grand_total);
        $cart = $service->updateItems($cart->items->first()->id, 3);
        $this->assertEquals(37.02, $cart->grand_total);
        $this->expectException(CartClientException::class);
        $service->addProduct($product, -1);
    }
    public function test_checkout_uses_current_price_and_discount_and_cannot_repeat(): void
    {
        config(['shopping-cart.order_number_generator_class' => OrderNumber::class, 'shopping-cart.models.skus.model' => Product::class]);
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 10]);
        $product->product_id = $product->id;
        // Product IDs and SKU IDs are distinct snapshots in the host.
        Schema::table('products', fn (Blueprint $t) => $t->string('product_id')->nullable());
        $product->save();
        DB::table('product_skus')->insert(['id' => $product->id]);
        $cart = $service->addProduct($product, 2);
        $item = $cart->items->first();
        $item->discount_amount = 3;
        $item->base_discount_amount = 3;
        $item->save();
        $product->update(['price' => 12.34]);
        $response = app(CheckoutController::class)(request());
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $checkout = Checkout::firstOrFail();
        $this->assertEquals(24.68, $checkout->subtotal_amount);
        $this->assertEquals(3, $checkout->discount_amount);
        $this->assertEquals(21.68, $checkout->total_amount);
        $this->assertEquals(12.34, $checkout->items->first()->price_snapshot);
        $this->assertEquals(3, $checkout->items->first()->discount_amount);
        $this->assertEquals($checkout->total_amount, $checkout->toOrderArray()['items'][0]['total_amount']);
        $again = app(CheckoutController::class)(request());
        $this->assertSame(400, $again->getStatusCode());
        $this->assertSame(1, Checkout::count());
    }
    public function test_checkout_expiration_and_paid_status_are_consistent(): void
    {
        $checkout = new Checkout(['status' => 1, 'expires_at' => now()->addHour()]);
        $this->assertFalse($checkout->canProceed());
        $checkout->status = 0;
        $checkout->expires_at = now()->subSecond();
        $this->assertTrue($checkout->isExpired());
    }
    public function test_buy_now_restores_regular_cart_without_cross_user_activation(): void
    {
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 10]);
        $regular = $service->addProduct($product, 1);
        request()->merge(['is_buy_now' => true]);
        $service->deactivateCurrentCartIfBuyNowIsActive();
        $temporary = $service->addProduct($product, 2, true);
        $this->assertTrue($temporary->is_temp);
        $service->deActivateCart();
        $service->activateCartIfCacheHasDeactivatedCartId();
        $this->assertSame($regular->id, $service->getCart()->id);
        $this->assertSame(1, $service->getCart()->items->first()->quantity);
    }
    public function test_unvalidated_coupon_is_rejected(): void
    {
        $service = app(ShoppingCart::class);
        $service->addProduct(Product::create(['price' => 10]), 1);
        $this->expectException(CartClientException::class);
        $service->setCouponCode('anything');
    }
    public function test_redis_failure_falls_back_without_losing_mutation(): void
    {
        $storage = new RedisCartStorage(new DatabaseCartStorage, 'test', 60, 'missing');
        $cart = $storage->createCart(['customer_id' => 1]);
        $product = Product::create(['price' => 10]);
        $storage->addCartItem($cart->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 2]);
        $this->assertSame($cart->id, $storage->getCart('1')->id);
        $this->assertCount(1, $storage->getCart('1')->items);
    }

    public function test_selection_api_rejects_other_users_item(): void
    {
        $storage = new DatabaseCartStorage;
        $own = $storage->createCart(['customer_id' => 1]);
        DB::table('users')->insert(['id' => 2, 'name' => 'Two']);
        $other = $storage->createCart(['customer_id' => 2]);
        $product = Product::create(['price' => 10]);
        $item = $storage->addCartItem($other->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 1]);
        $this->patchJson('/modules/shopping-cart/api/cart/item-selection', ['item_id' => $item->id, 'selected' => true])->assertForbidden();
        $this->assertFalse($item->fresh()->selected);
    }
    public function test_cached_service_reads_updated_totals_after_writes(): void
    {
        config(['cache.default' => 'array']);
        $storage = new CacheCartStorage(new DatabaseCartStorage, 'service', 60, 'array');
        $service = new ShoppingCart($storage);
        $product = Product::create(['price' => 3.21]);
        $cart = $service->addProduct($product, 1);
        $service->getCart();
        $service->updateItems($cart->items->first()->id, 3);
        $cart = $service->getCart();
        $this->assertEquals(9.63, $cart->grand_total);
        $service->removeItem($cart->items->first()->id);
        $this->assertCount(0, $service->getCart()->items);
        $this->assertEquals(0, $service->getCart()->grand_total);
    }
    public function test_repeated_buy_now_preserves_original_cart(): void
    {
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 10]);
        $regular = $service->addProduct($product, 1);
        request()->merge(['is_buy_now' => true]);
        $service->deactivateCurrentCartIfBuyNowIsActive();
        $service->addProduct($product, 2, true);
        $service->deactivateCurrentCartIfBuyNowIsActive();
        $service->addProduct($product, 3, true);
        $service->deActivateCart();
        $service->activateCartIfCacheHasDeactivatedCartId();
        $this->assertSame($regular->id, $service->getCart()->id);
    }

    public function test_stale_cached_snapshot_cannot_override_database_state(): void
    {
        config(['cache.default' => 'array']);
        $db = new DatabaseCartStorage;
        $cart = $db->createCart(['customer_id' => 1]);
        $cache = new CacheCartStorage($db, 'stale', 60, 'array');
        $product = Product::create(['price' => 10]);
        $db->addCartItem($cart->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 1]);
        $cache->getCart('1');
        $db->emptyCart($cart->id); // Models writes or an outage may leave the old cache key intact.
        $this->assertCount(0, $cache->getCart('1')->items);
    }
    public function test_activation_keeps_one_active_cart(): void
    {
        $service = app(ShoppingCart::class);
        $first = $service->create();
        $first->is_active = false;
        $first->save();
        $second = $service->create();
        $service->activateCart($first->id);
        $this->assertSame(1, Cart::where('customer_id', 1)->where('is_active', true)->count());
        $this->assertSame($first->id, $service->getCart()->id);
    }

    public function test_provider_migration_registration_installs_all_tables(): void
    {
        foreach (array_reverse(glob(__DIR__ . '/../../database/migrations/*.php')) as $file) { (require $file)->down(); }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('checkout_items'));
        $this->assertTrue(Schema::hasTable('shopping_cart_items'));
    }
    public function test_missing_stock_validator_fails_closed_and_keeps_cart(): void
    {
        config(['shopping-cart.models.skus.model' => Product::class, 'shopping-cart.checkout.validator' => null]);
        $service = app(ShoppingCart::class);
        $cart = $service->addProduct(Product::create(['price' => 10]), 1);
        try { app(CheckoutController::class)(request());
            $this->fail('Checkout must require stock validation'); } catch (HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertSame(0, Checkout::count());
        $this->assertCount(1, $service->getCart()->items);
    }
    public function test_expired_coupon_is_revalidated_at_checkout(): void
    {
        config(['shopping-cart.models.skus.model' => Product::class]);
        $service = app(ShoppingCart::class);
        $cart = $service->addProduct(Product::create(['price' => 10]), 1);
        $service->setCouponCode('coupon', fn () => true);
        config(['shopping-cart.coupon_validator' => fn () => false]);
        $this->expectException(CartClientException::class);
        try { app(CheckoutController::class)(request()); } finally { $this->assertSame(0, Checkout::count());
            $this->assertCount(1, $service->getCart()->items); }
    }
    public function test_update_and_remove_reject_foreign_items(): void
    {
        $service = app(ShoppingCart::class);
        $service->addProduct(Product::create(['price' => 10]), 1);
        DB::table('users')->insert(['id' => 2, 'name' => 'Two']);
        $storage = new DatabaseCartStorage;
        $other = $storage->createCart(['customer_id' => 2]);
        $product = Product::create(['price' => 10]);
        $item = $storage->addCartItem($other->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 1]);
        foreach ([fn () => $service->updateItems($item->id, 4), fn () => $service->removeItem($item->id)] as $action) {
            try { $action();
                $this->fail('Foreign mutation must fail'); } catch (CartClientException $e) { $this->assertSame(1, $item->fresh()->quantity); }
        }
    }

    public function test_model_helpers_accept_ulids_and_preserve_decimal_prices(): void
    {
        $service = app(ShoppingCart::class);
        $product = Product::create(['price' => 12.34]);
        $cart = $service->addProduct($product, 2);
        $this->assertTrue($cart->hasProduct($product->id));
        $this->assertEquals(24.68, $cart->calculatedPriceByQuantity());
        $this->assertSame($product->id, $cart->findItemByProductId($product->id)->itemable_id);
    }

    public function test_failed_bulk_writes_are_reported(): void
    {
        app(ShoppingCart::class)->addProduct(Product::create(['price' => 10]), 1);
        $storage = new class extends DatabaseCartStorage {
            public function toggleAllItems(int|string $cartId, bool $selected): bool { return false; }
            public function emptyCart(int|string $cartId): bool { return false; }
        };
        $service = new ShoppingCart($storage);
        foreach ([fn () => $service->setAllCartItemsSelected(false), fn () => $service->emptyCart()] as $action) {
            try { $action();
                $this->fail('Expected failed write to abort'); } catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        }
        $this->assertCount(1, $service->getCart()->items);
    }
}
class OrderNumber
{ public function __invoke(): string { return (string) Str::ulid(); } }
