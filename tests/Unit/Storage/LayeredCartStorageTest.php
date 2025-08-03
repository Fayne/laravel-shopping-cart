<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use MobileNowGroup\ShoppingCart\Storage\LayeredCartStorage;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class LayeredCartStorageTest extends TestCase
{
    use RefreshDatabase;

    protected LayeredCartStorage $storage;
    protected $mockRedisStorage;
    protected $mockCacheStorage;
    protected $mockDatabaseStorage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRedisStorage = Mockery::mock(CartStorageInterface::class);
        $this->mockCacheStorage = Mockery::mock(CartStorageInterface::class);
        $this->mockDatabaseStorage = Mockery::mock(CartStorageInterface::class);
        
        $this->storage = new LayeredCartStorage(
            $this->mockDatabaseStorage,
            $this->mockCacheStorage,
            $this->mockRedisStorage
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_tries_redis_first_then_cache_then_database_for_get_cart()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        // Redis returns null, cache returns null, database returns cart
        $this->mockRedisStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn(null);
            
        $this->mockCacheStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn(null);
            
        $this->mockDatabaseStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_returns_cart_from_redis_when_available()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockRedisStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);
            
        // Cache and database should not be called
        $this->mockCacheStorage->shouldNotReceive('getCart');
        $this->mockDatabaseStorage->shouldNotReceive('getCart');

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_returns_cart_from_cache_when_redis_fails()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockRedisStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn(null);
            
        $this->mockCacheStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);
            
        // Database should not be called
        $this->mockDatabaseStorage->shouldNotReceive('getCart');

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_creates_cart_in_all_layers()
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
            
        $this->mockCacheStorage
            ->shouldReceive('createCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);
            
        $this->mockRedisStorage
            ->shouldReceive('createCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);

        $result = $this->storage->createCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_updates_cart_in_all_layers()
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
            
        $this->mockCacheStorage
            ->shouldReceive('updateCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);
            
        $this->mockRedisStorage
            ->shouldReceive('updateCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);

        $result = $this->storage->updateCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_deletes_cart_from_all_layers()
    {
        $customerId = 'user123';

        $this->mockDatabaseStorage
            ->shouldReceive('deleteCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockCacheStorage
            ->shouldReceive('deleteCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockRedisStorage
            ->shouldReceive('deleteCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);

        $result = $this->storage->deleteCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_adds_item_to_all_layers()
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
            
        $this->mockCacheStorage
            ->shouldReceive('addItem')
            ->once()
            ->with($cartId, $itemData)
            ->andReturn($item);
            
        $this->mockRedisStorage
            ->shouldReceive('addItem')
            ->once()
            ->with($cartId, $itemData)
            ->andReturn($item);

        $result = $this->storage->addItem($cartId, $itemData);

        $this->assertEquals($item, $result);
    }

    /** @test */
    public function it_updates_items_in_all_layers()
    {
        $cartId = '1';
        $items = [['id' => 1, 'quantity' => 5]];

        $this->mockDatabaseStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartId, $items)
            ->andReturn(true);
            
        $this->mockCacheStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartId, $items)
            ->andReturn(true);
            
        $this->mockRedisStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartId, $items)
            ->andReturn(true);

        $result = $this->storage->updateItems($cartId, $items);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_removes_item_from_all_layers()
    {
        $cartId = '1';
        $itemId = 1;

        $this->mockDatabaseStorage
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn(true);
            
        $this->mockCacheStorage
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn(true);
            
        $this->mockRedisStorage
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn(true);

        $result = $this->storage->removeItem($cartId, $itemId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_empties_cart_in_all_layers()
    {
        $customerId = 'user123';

        $this->mockDatabaseStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockCacheStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockRedisStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);

        $result = $this->storage->emptyCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_toggles_items_selection_in_all_layers()
    {
        $cartId = '1';
        $itemIds = [1, 2];
        $selected = true;

        $this->mockDatabaseStorage
            ->shouldReceive('toggleItemsSelection')
            ->once()
            ->with($cartId, $itemIds, $selected)
            ->andReturn(true);
            
        $this->mockCacheStorage
            ->shouldReceive('toggleItemsSelection')
            ->once()
            ->with($cartId, $itemIds, $selected)
            ->andReturn(true);
            
        $this->mockRedisStorage
            ->shouldReceive('toggleItemsSelection')
            ->once()
            ->with($cartId, $itemIds, $selected)
            ->andReturn(true);

        $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_items_count_from_fastest_available_layer()
    {
        $customerId = 'user123';
        $count = 5;

        $this->mockRedisStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn($count);
            
        // Cache and database should not be called
        $this->mockCacheStorage->shouldNotReceive('getItemsCount');
        $this->mockDatabaseStorage->shouldNotReceive('getItemsCount');

        $result = $this->storage->getItemsCount($customerId);

        $this->assertEquals($count, $result);
    }

    /** @test */
    public function it_gets_items_qty_from_fastest_available_layer()
    {
        $customerId = 'user123';
        $qty = 10;

        $this->mockRedisStorage
            ->shouldReceive('getItemsQty')
            ->once()
            ->with($customerId)
            ->andReturn($qty);
            
        // Cache and database should not be called
        $this->mockCacheStorage->shouldNotReceive('getItemsQty');
        $this->mockDatabaseStorage->shouldNotReceive('getItemsQty');

        $result = $this->storage->getItemsQty($customerId);

        $this->assertEquals($qty, $result);
    }

    /** @test */
    public function it_falls_back_through_layers_for_items_count()
    {
        $customerId = 'user123';
        $count = 3;

        $this->mockRedisStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn(null);
            
        $this->mockCacheStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn(null);
            
        $this->mockDatabaseStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn($count);

        $result = $this->storage->getItemsCount($customerId);

        $this->assertEquals($count, $result);
    }

    /** @test */
    public function it_gets_cart_items_from_fastest_available_layer()
    {
        $cartId = '1';
        $items = collect([
            new ShoppingCartItem([
                'id' => 1,
                'cart_id' => $cartId,
                'product_id' => 1,
                'quantity' => 2,
                'price' => 10.00,
                'name' => 'Test Product',
            ])
        ]);

        $this->mockRedisStorage
            ->shouldReceive('getCartItems')
            ->once()
            ->with($cartId)
            ->andReturn($items);
            
        // Cache and database should not be called
        $this->mockCacheStorage->shouldNotReceive('getCartItems');
        $this->mockDatabaseStorage->shouldNotReceive('getCartItems');

        $result = $this->storage->getCartItems($cartId);

        $this->assertEquals($items, $result);
    }

    /** @test */
    public function it_works_with_only_database_storage()
    {
        $storage = new LayeredCartStorage($this->mockDatabaseStorage);
        
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

        $result = $storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_works_with_database_and_cache_only()
    {
        $storage = new LayeredCartStorage($this->mockDatabaseStorage, $this->mockCacheStorage);
        
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockCacheStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);
            
        // Database should not be called
        $this->mockDatabaseStorage->shouldNotReceive('getCart');

        $result = $storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }
}