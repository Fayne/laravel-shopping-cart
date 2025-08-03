<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use MobileNowGroup\ShoppingCart\Storage\CacheCartStorage;
use MobileNowGroup\ShoppingCart\Storage\DatabaseCartStorage;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class CacheCartStorageTest extends TestCase
{
    use RefreshDatabase;

    protected CacheCartStorage $storage;
    protected $mockDatabaseStorage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        
        $this->mockDatabaseStorage = Mockery::mock(DatabaseCartStorage::class);
        $this->storage = new CacheCartStorage($this->mockDatabaseStorage);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_cart_from_cache()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->once()
            ->with("cart:cart:{$customerId}", 3600, Mockery::type('callable'))
            ->andReturn($cart);

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_falls_back_to_database_when_cache_miss()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockDatabaseStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->once()
            ->with("cart:cart:{$customerId}", 3600, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_create_cart_and_clear_cache()
    {
        $customerId = 'user123';
        $data = ['customer_name' => 'John Doe'];
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockDatabaseStorage
            ->shouldReceive('createCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:cart:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:stats:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cart->id}");

        $result = $this->storage->createCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_update_cart_and_clear_cache()
    {
        $customerId = 'user123';
        $data = ['customer_name' => 'Jane Doe'];
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'Jane Doe',
        ]);

        $this->mockDatabaseStorage
            ->shouldReceive('updateCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);

        Cache::shouldReceive('store')
            ->times(3)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:cart:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:stats:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cart->id}");

        $result = $this->storage->updateCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_delete_cart_and_clear_cache()
    {
        $customerId = 'user123';

        $this->mockDatabaseStorage
            ->shouldReceive('deleteCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);

        Cache::shouldReceive('store')
            ->times(2)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:cart:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:stats:{$customerId}");

        $result = $this->storage->deleteCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_add_item_and_clear_cache()
    {
        $cartId = '1';
        $itemData = [
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ];
        $item = new ShoppingCartItem(array_merge($itemData, ['id' => 1, 'cart_id' => $cartId]));

        $this->mockDatabaseStorage
            ->shouldReceive('addItem')
            ->once()
            ->with($cartId, $itemData)
            ->andReturn($item);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cartId}");

        $result = $this->storage->addItem($cartId, $itemData);

        $this->assertEquals($item, $result);
    }

    /** @test */
    public function it_can_update_items_and_clear_cache()
    {
        $cartId = '1';
        $items = [['id' => 1, 'quantity' => 5]];

        $this->mockDatabaseStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartId, $items)
            ->andReturn(true);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cartId}");

        $result = $this->storage->updateItems($cartId, $items);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_remove_item_and_clear_cache()
    {
        $cartId = '1';
        $itemId = 1;

        $this->mockDatabaseStorage
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn(true);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cartId}");

        $result = $this->storage->removeItem($cartId, $itemId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_empty_cart_and_clear_cache()
    {
        $customerId = 'user123';

        $this->mockDatabaseStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);

        Cache::shouldReceive('store')
            ->times(2)
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:cart:{$customerId}");
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:stats:{$customerId}");

        $result = $this->storage->emptyCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_toggle_items_selection_and_clear_cache()
    {
        $cartId = '1';
        $itemIds = [1, 2];
        $selected = true;

        $this->mockDatabaseStorage
            ->shouldReceive('toggleItemsSelection')
            ->once()
            ->with($cartId, $itemIds, $selected)
            ->andReturn(true);

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('forget')
            ->once()
            ->with("cart:items:{$cartId}");

        $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_items_count_from_cache()
    {
        $customerId = 'user123';
        $count = 5;

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->once()
            ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
            ->andReturn(['items_count' => $count]);

        $result = $this->storage->getItemsCount($customerId);

        $this->assertEquals($count, $result);
    }

    /** @test */
    public function it_can_get_items_qty_from_cache()
    {
        $customerId = 'user123';
        $qty = 10;

        Cache::shouldReceive('store')
            ->once()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->once()
            ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
            ->andReturn(['items_qty' => $qty]);

        $result = $this->storage->getItemsQty($customerId);

        $this->assertEquals($qty, $result);
    }

    /** @test */
    public function it_falls_back_to_database_for_stats_when_cache_miss()
    {
        $customerId = 'user123';
        $count = 3;
        $qty = 8;

        $this->mockDatabaseStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn($count);
            
        $this->mockDatabaseStorage
            ->shouldReceive('getItemsQty')
            ->once()
            ->with($customerId)
            ->andReturn($qty);

        Cache::shouldReceive('store')
            ->twice()
            ->with('default')
            ->andReturnSelf();
            
        Cache::shouldReceive('remember')
            ->twice()
            ->with("cart:stats:{$customerId}", 1800, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $countResult = $this->storage->getItemsCount($customerId);
        $qtyResult = $this->storage->getItemsQty($customerId);

        $this->assertEquals($count, $countResult);
        $this->assertEquals($qty, $qtyResult);
    }
}