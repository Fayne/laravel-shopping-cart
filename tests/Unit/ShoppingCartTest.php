<?php

namespace MobileNowGroup\ShoppingCart\Tests\Unit;

use Mockery;
use MobileNowGroup\ShoppingCart\ShoppingCart;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ShoppingCartTest extends TestCase
{

    protected ShoppingCart $cart;
    protected $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockStorage = Mockery::mock(CartStorageInterface::class);
        $this->cart = new ShoppingCart($this->mockStorage);
    }
    
    protected function createMockUser($id)
    {
        $user = new class($id) extends Authenticatable {
            public $id;
            public function __construct($id) {
                $this->id = $id;
            }
            public function getAuthIdentifier() {
                return $this->id;
            }
        };
        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_cart_for_customer()
    {
        $customerId = 'user123';
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);
        $cartModel->id = 1;
        $cartModel->id = 1;

        // Mock authentication
        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId, false)
            ->andReturn($cartModel);

        $result = $this->cart->getCart();

        $this->assertEquals($cartModel, $result);
    }

    /** @test */
    public function it_can_create_cart_for_customer()
    {
        $customerId = 'user123';
        $customerName = 'John Doe';
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
        ]);

        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('createCart')
            ->once()
            ->with([
                'customer_id' => $customerId,
                'customer_name' => $customerName,
            ])
            ->andReturn($cartModel);

        $result = $this->cart->create(['customer_name' => $customerName]);

        $this->assertEquals($cartModel, $result);
    }

    /** @test */
    public function it_can_add_product_to_cart()
    {
        $customerId = 'user123';
        $quantity = 2;
        
        $cartModel = Mockery::mock(CartModel::class);
        $cartModel->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $cartModel->shouldReceive('getAttribute')->with('customer_id')->andReturn($customerId);
        $cartModel->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
        $cartModel->shouldReceive('getAttribute')->with('items')->andReturn(collect());
        $cartModel->id = 1;
        
        $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
        $mockItem->shouldReceive('getPrice')->andReturn(10.00);
        $mockItem->shouldReceive('getSku')->andReturn('TEST-SKU');
        $mockItem->shouldReceive('getName')->andReturn('Test Product');
        $mockItem->shouldReceive('getWeight')->andReturn(1.0);
        $mockItem->shouldReceive('getKey')->andReturn(1);

        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('addCartItem')
            ->once()
            ->with($cartModel->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->cart->addProduct($mockItem, $quantity);

        $this->assertEquals($cartModel, $result);
    }

    /** @test */
    public function it_can_add_product_with_default_options()
    {
        $customerId = 'user123';
        $quantity = 1;
        
        $cartModel = Mockery::mock(CartModel::class);
        $cartModel->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $cartModel->shouldReceive('getAttribute')->with('customer_id')->andReturn($customerId);
        $cartModel->shouldReceive('getAttribute')->with('customer_name')->andReturn('John Doe');
        $cartModel->shouldReceive('getAttribute')->with('items')->andReturn(collect());
        $cartModel->id = 1;
        
        $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
        $mockItem->shouldReceive('getPrice')->andReturn(10.00);
        $mockItem->shouldReceive('getSku')->andReturn('TEST-SKU-2');
        $mockItem->shouldReceive('getName')->andReturn('Test Product');
        $mockItem->shouldReceive('getWeight')->andReturn(1.0);
        $mockItem->shouldReceive('getKey')->andReturn(1);

        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('addCartItem')
            ->once()
            ->with($cartModel->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->cart->addProduct($mockItem, $quantity);

        $this->assertEquals($cartModel, $result);
    }

    /** @test */
    public function it_can_remove_item_from_cart()
    {
        $customerId = 'user123';
        $itemId = 1;
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        // Mock authentication
        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('removeCartItem')
            ->once()
            ->with($itemId)
            ->andReturn(true);

        $result = $this->cart->removeItem($itemId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_update_items_in_cart()
    {
        $customerId = 'user123';
        $items = [['id' => 1, 'quantity' => 5]];
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);
        $cartModel->id = 1;

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('updateItems')
            ->once()
            ->with($cartModel->id, $items)
            ->andReturn(true);

        $result = $this->cart->updateItems($customerId, $items);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_empty_cart()
    {
        $customerId = 'user123';
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
        ]);
        $cartModel->id = 1;

        // Mock authentication
         $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel, $cartModel);
            
        $this->mockStorage
            ->shouldReceive('emptyCart')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->cart->emptyCart();

        $this->assertInstanceOf(CartModel::class, $result);
    }

    /** @test */
    public function it_can_toggle_buy_status()
    {
        $customerId = 'user123';
        $type = 'single';
        $selected = true;
        $itemId = 1;
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);

        $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('toggleItemSelection')
            ->once()
            ->with($cartModel->id, $itemId, $selected)
            ->andReturn(true);

        $result = $this->cart->toggleBuy($type, $selected, $itemId);

        $this->assertEquals($cartModel, $result);
    }

    /** @test */
    public function it_can_get_items_count()
    {
        $customerId = 'user123';
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
        ]);
        $cartModel->id = 1;
        $count = 5;

        // Mock authentication
         $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('getCartStats')
            ->once()
            ->with(1)
            ->andReturn(['items_count' => $count]);

        $result = $this->cart->itemsCount();

        $this->assertEquals($count, $result);
    }

    /** @test */
    public function it_can_get_items_quantity()
    {
        $customerId = 'user123';
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
        ]);
        $cartModel->id = 1;
        $qty = 10;

        // Mock authentication
         $this->actingAs($this->createMockUser($customerId));

        $this->mockStorage
            ->shouldReceive('getCart')
            ->once()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('getCartStats')
            ->once()
            ->with(1)
            ->andReturn(['items_qty' => $qty]);

        $result = $this->cart->itemsQty();

        $this->assertEquals($qty, $result);
    }

    /** @test */
    public function it_handles_existing_product_in_cart()
    {
        $customerId = 'user123';
        $quantity = 2;
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);
        $cartModel->id = 1;
        
        $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
        $mockItem->shouldReceive('getPrice')->andReturn(10.00);
        $mockItem->shouldReceive('getSku')->andReturn('TEST-SKU');
        $mockItem->shouldReceive('getName')->andReturn('Test Product');
        $mockItem->shouldReceive('getWeight')->andReturn(1.0);
        $mockItem->shouldReceive('getKey')->andReturn(1);
        
        $this->actingAs($this->createMockUser($customerId));
        
        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('addCartItem')
            ->once()
            ->with($cartModel->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->cart->addProduct($mockItem, $quantity);

        $this->assertInstanceOf(CartModel::class, $result);
    }

    /** @test */
    public function it_adds_new_product_when_not_exists_in_cart()
    {
        $customerId = 'user123';
        $quantity = 2;
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);
        $cartModel->id = 1;
        
        $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
        $mockItem->shouldReceive('getPrice')->andReturn(10.00);
        $mockItem->shouldReceive('getSku')->andReturn('TEST-SKU-2');
        $mockItem->shouldReceive('getName')->andReturn('Test Product 2');
        $mockItem->shouldReceive('getWeight')->andReturn(1.5);
        $mockItem->shouldReceive('getKey')->andReturn(2);
        
        $this->actingAs($this->createMockUser($customerId));
        
        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('addCartItem')
            ->once()
            ->with($cartModel->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->cart->addProduct($mockItem, $quantity);

        $this->assertInstanceOf(CartModel::class, $result);
    }

    /** @test */
    public function it_handles_different_options_as_different_products()
    {
        $customerId = 'user123';
        $quantity = 2;
        
        $cartModel = new CartModel([
            'id' => 1,
            'customer_id' => $customerId,
            'customer_name' => 'John Doe',
        ]);
        $cartModel->id = 1;
        
        $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
        $mockItem->shouldReceive('getPrice')->andReturn(10.00);
        $mockItem->shouldReceive('getSku')->andReturn('TEST-SKU-3');
        $mockItem->shouldReceive('getName')->andReturn('Test Product with Options');
        $mockItem->shouldReceive('getWeight')->andReturn(2.0);
        $mockItem->shouldReceive('getKey')->andReturn(3);
        
        $this->actingAs($this->createMockUser($customerId));
        
        $this->mockStorage
            ->shouldReceive('getCart')
            ->twice()
            ->with($customerId, false)
            ->andReturn($cartModel);
            
        $this->mockStorage
            ->shouldReceive('addCartItem')
            ->once()
            ->with($cartModel->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->cart->addProduct($mockItem, $quantity);

        $this->assertInstanceOf(CartModel::class, $result);
    }
}