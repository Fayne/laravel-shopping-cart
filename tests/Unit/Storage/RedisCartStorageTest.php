<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use MobileNowGroup\ShoppingCart\Storage\RedisCartStorage;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;

class RedisCartStorageTest extends TestCase
{
    use RefreshDatabase;

    protected RedisCartStorage $storage;
    protected $mockFallbackStorage;
    protected $mockRedis;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockFallbackStorage = Mockery::mock(CartStorageInterface::class);
        $this->mockRedis = Mockery::mock();
        
        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($this->mockRedis);
            
        $this->storage = new RedisCartStorage($this->mockFallbackStorage);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_cart_from_redis()
    {
        $customerId = 'user123';
        $cartData = [
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ];
        
        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:cart:{$customerId}")
            ->andReturn(json_encode($cartData));

        $result = $this->storage->getCart($customerId);

        $this->assertInstanceOf(ShoppingCart::class, $result);
        $this->assertEquals($customerId, $result->customer_id);
        $this->assertEquals('John Doe', $result->customer_name);
    }

    /** @test */
    public function it_falls_back_to_fallback_storage_when_redis_fails()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:cart:{$customerId}")
            ->andReturn(null);
            
        $this->mockFallbackStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);
            
        $this->mockRedis
            ->shouldReceive('setex')
            ->once()
            ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_create_cart_and_cache_in_redis()
    {
        $customerId = 'user123';
        $data = ['customer_name' => 'John Doe'];
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockFallbackStorage
            ->shouldReceive('createCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);
            
        $this->mockRedis
            ->shouldReceive('setex')
            ->once()
            ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:stats:{$customerId}", "cart:items:{$cart->id}"]);

        $result = $this->storage->createCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_update_cart_and_update_redis_cache()
    {
        $customerId = 'user123';
        $data = ['customer_name' => 'Jane Doe'];
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'Jane Doe',
        ]);

        $this->mockFallbackStorage
            ->shouldReceive('updateCart')
            ->once()
            ->with($customerId, $data)
            ->andReturn($cart);
            
        $this->mockRedis
            ->shouldReceive('setex')
            ->once()
            ->with("cart:cart:{$customerId}", 3600, json_encode($cart->toArray()));
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:stats:{$customerId}", "cart:items:{$cart->id}"]);

        $result = $this->storage->updateCart($customerId, $data);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_delete_cart_and_clear_redis_cache()
    {
        $customerId = 'user123';

        $this->mockFallbackStorage
            ->shouldReceive('deleteCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:cart:{$customerId}", "cart:stats:{$customerId}"]);

        $result = $this->storage->deleteCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_add_item_and_clear_redis_cache()
    {
        $cartId = '1';
        $itemData = [
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.00,
            'name' => 'Test Product',
        ];
        $item = new ShoppingCartItem(array_merge($itemData, ['id' => 1, 'cart_id' => $cartId]));

        $this->mockFallbackStorage
            ->shouldReceive('addItem')
            ->once()
            ->with($cartId, $itemData)
            ->andReturn($item);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:items:{$cartId}"]);

        $result = $this->storage->addItem($cartId, $itemData);

        $this->assertEquals($item, $result);
    }

    /** @test */
    public function it_can_update_items_and_clear_redis_cache()
    {
        $cartId = '1';
        $items = [['id' => 1, 'quantity' => 5]];

        $this->mockFallbackStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartId, $items)
            ->andReturn(true);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:items:{$cartId}"]);

        $result = $this->storage->updateItems($cartId, $items);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_remove_item_and_clear_redis_cache()
    {
        $cartId = '1';
        $itemId = 1;

        $this->mockFallbackStorage
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn(true);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:items:{$cartId}"]);

        $result = $this->storage->removeItem($cartId, $itemId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_empty_cart_and_clear_redis_cache()
    {
        $customerId = 'user123';

        $this->mockFallbackStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with($customerId)
            ->andReturn(true);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:cart:{$customerId}", "cart:stats:{$customerId}"]);

        $result = $this->storage->emptyCart($customerId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_toggle_items_selection_and_clear_redis_cache()
    {
        $cartId = '1';
        $itemIds = [1, 2];
        $selected = true;

        $this->mockFallbackStorage
            ->shouldReceive('toggleItemsSelection')
            ->once()
            ->with($cartId, $itemIds, $selected)
            ->andReturn(true);
            
        $this->mockRedis
            ->shouldReceive('del')
            ->once()
            ->with(["cart:items:{$cartId}"]);

        $result = $this->storage->toggleItemsSelection($cartId, $itemIds, $selected);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_items_count_from_redis()
    {
        $customerId = 'user123';
        $statsData = ['items_count' => 5, 'items_qty' => 10];
        
        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:stats:{$customerId}")
            ->andReturn(json_encode($statsData));

        $result = $this->storage->getItemsCount($customerId);

        $this->assertEquals(5, $result);
    }

    /** @test */
    public function it_can_get_items_qty_from_redis()
    {
        $customerId = 'user123';
        $statsData = ['items_count' => 5, 'items_qty' => 10];
        
        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:stats:{$customerId}")
            ->andReturn(json_encode($statsData));

        $result = $this->storage->getItemsQty($customerId);

        $this->assertEquals(10, $result);
    }

    /** @test */
    public function it_falls_back_to_fallback_storage_for_stats_when_redis_fails()
    {
        $customerId = 'user123';
        $count = 3;
        $qty = 8;

        $this->mockRedis
            ->shouldReceive('get')
            ->twice()
            ->with("cart:stats:{$customerId}")
            ->andReturn(null);
            
        $this->mockFallbackStorage
            ->shouldReceive('getItemsCount')
            ->once()
            ->with($customerId)
            ->andReturn($count);
            
        $this->mockFallbackStorage
            ->shouldReceive('getItemsQty')
            ->once()
            ->with($customerId)
            ->andReturn($qty);
            
        $this->mockRedis
            ->shouldReceive('setex')
            ->twice()
            ->with("cart:stats:{$customerId}", 1800, json_encode(['items_count' => $count, 'items_qty' => $qty]));

        $countResult = $this->storage->getItemsCount($customerId);
        $qtyResult = $this->storage->getItemsQty($customerId);

        $this->assertEquals($count, $countResult);
        $this->assertEquals($qty, $qtyResult);
    }

    /** @test */
    public function it_handles_redis_exceptions_gracefully()
    {
        $customerId = 'user123';
        $cart = new ShoppingCart([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:cart:{$customerId}")
            ->andThrow(new \Exception('Redis connection failed'));
            
        $this->mockFallbackStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cart);

        $result = $this->storage->getCart($customerId);

        $this->assertEquals($cart, $result);
    }

    /** @test */
    public function it_can_get_cart_items_from_redis()
    {
        $cartId = '1';
        $itemsData = [
            [
                'id' => 1,
                'cart_id' => $cartId,
                'product_id' => 1,
                'quantity' => 2,
                'price' => 10.00,
                'name' => 'Test Product 1',
            ],
            [
                'id' => 2,
                'cart_id' => $cartId,
                'product_id' => 2,
                'quantity' => 1,
                'price' => 20.00,
                'name' => 'Test Product 2',
            ],
        ];
        
        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:items:{$cartId}")
            ->andReturn(json_encode($itemsData));

        $result = $this->storage->getCartItems($cartId);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ShoppingCartItem::class, $result[0]);
        $this->assertEquals('Test Product 1', $result[0]->name);
    }

    /** @test */
    public function it_falls_back_to_fallback_storage_for_items_when_redis_fails()
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

        $this->mockRedis
            ->shouldReceive('get')
            ->once()
            ->with("cart:items:{$cartId}")
            ->andReturn(null);
            
        $this->mockFallbackStorage
            ->shouldReceive('getCartItems')
            ->once()
            ->with($cartId)
            ->andReturn($items);
            
        $this->mockRedis
            ->shouldReceive('setex')
            ->once()
            ->with("cart:items:{$cartId}", 3600, json_encode($items->toArray()));

        $result = $this->storage->getCartItems($cartId);

        $this->assertEquals($items, $result);
    }
}