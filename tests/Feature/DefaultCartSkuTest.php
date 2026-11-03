<?php

namespace Fayne\ShoppingCart\Tests\Feature;

use Fayne\ShoppingCart\Models\CartSku;
use Fayne\ShoppingCart\ShoppingCart;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Schema;

class DefaultCartSkuTest extends IntegrationTestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('shopping-cart.models.skus.model', (require __DIR__ . '/../../config/shopping-cart.php')['models']['skus']['model']);
    }

    public function test_package_sku_supports_cart_and_quote_without_host_catalog_tables(): void
    {
        Schema::drop('products');
        Schema::drop('product_skus');

        $this->assertSame(CartSku::class, (require __DIR__ . '/../../config/shopping-cart.php')['models']['skus']['model']);
        $sku = CartSku::create(['sku' => 'DEMO', 'name' => 'Demo SKU', 'price_amount' => 1990]);
        $service = app(ShoppingCart::class);
        $cart = $service->addProduct($sku, 2);

        $this->assertEquals(39.80, $cart->grand_total);
        $this->assertSame($sku->id, $service->selectedItemsForQuote()[0]['sku_id']);

        config(['shopping-cart.order_number_generator_class' => DefaultSkuOrderNumber::class]);
        $response = app(\Fayne\ShoppingCart\Http\Controllers\CheckoutController::class)(request());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('checkout_items', ['sku_id' => $sku->id, 'product_id' => null, 'quantity' => 2]);
    }
}

class DefaultSkuOrderNumber
{
    public function __invoke(): string
    {
        return 'default-sku-checkout';
    }
}
