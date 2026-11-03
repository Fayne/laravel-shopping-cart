<?php

namespace Fayne\ShoppingCart\Tests\Unit\Storage;

use Fayne\ShoppingCart\Storage\CartStorageManager;
use Fayne\ShoppingCart\Tests\Fixtures\Product;
use Fayne\ShoppingCart\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheCartStorageTest extends IntegrationTestCase
{
    public static function drivers(): array { return [['database'], ['cache'], ['redis'], ['layered']]; }

    #[DataProvider('drivers')]
    public function test_storage_contract_preserves_current_state(string $driver): void
    {
        config(['cache.default' => 'array', 'shopping-cart.storage.drivers.cache.store' => 'array', 'shopping-cart.storage.drivers.redis.connection' => 'missing']);
        $storage = app(CartStorageManager::class)->driver($driver);
        $cart = $storage->createCart(['customer_id' => 1]);
        $this->assertSame($cart->id, $storage->getCart('1')->id);
        $product = Product::create(['price' => 5]);
        $item = $storage->addCartItem($cart->id, ['itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => 2]);
        $this->assertSame(1, $storage->getItemsCount('1'));
        $this->assertSame(2, $storage->getItemsQty('1'));
        $storage->updateCartItem($item->id, ['quantity' => 3]);
        $this->assertSame(3, $storage->getCart('1')->items->first()->quantity);
        $storage->toggleAllItems($cart->id, true);
        $this->assertCount(1, $storage->getCart('1', true)->items);
        $storage->toggleAllItems($cart->id, false);
        $this->assertCount(0, $storage->getCart('1', true)->items);
        $this->assertCount(1, $storage->getCart('1')->items);
        $storage->removeCartItem($item->id);
        $this->assertNull($storage->getCartItem($item->id));
        $this->assertCount(0, $storage->getCart('1')->items);
        $storage->deleteCart('1');
        $this->assertNull($storage->getCart('1'));
    }
}
