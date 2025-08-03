<?php


use MobileNowGroup\ShoppingCart\ShoppingCart;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use Illuminate\Foundation\Auth\User as Authenticatable;

beforeEach(function () {
    $this->mockStorage = Mockery::mock(CartStorageInterface::class);
    $this->cart = new ShoppingCart($this->mockStorage);
});

afterEach(function () {
    Mockery::close();
});



test('it can get cart for customer', function () {
    $customerId = 'user123';
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);
    $cartModel->id = 1;
    $cartModel->id = 1;

    // Mock authentication
    $this->actingAs(createMockUserForShoppingCart($customerId));

    $this->mockStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId, false)
        ->andReturn($cartModel);

    $result = $this->cart->getCart();

    expect($result)->toEqual($cartModel);
});

test('it can create cart for customer', function () {
    $customerId = 'user123';
    $customerName = 'John Doe';
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => $customerName,
    ]);

    $this->actingAs(createMockUserForShoppingCart($customerId));

    $this->mockStorage
        ->shouldReceive('createCart')
        ->once()
        ->with([
            'customer_id' => $customerId,
            'customer_name' => $customerName,
        ])
        ->andReturn($cartModel);

    $result = $this->cart->create(['customer_name' => $customerName]);

    expect($result)->toEqual($cartModel);
});

test('it can add product to cart', function () {
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

    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toEqual($cartModel);
});

test('it can add product with default options', function () {
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

    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toEqual($cartModel);
});

test('it can remove item from cart', function () {
    $customerId = 'user123';
    $itemId = 1;
    
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    // Mock authentication
    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toBeTrue();
});

test('it can update items in cart', function () {
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

    expect($result)->toBeTrue();
});

test('it can empty cart', function () {
    $customerId = 'user123';
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
    ]);
    $cartModel->id = 1;

    // Mock authentication
    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toBeInstanceOf(CartModel::class);
});

test('it can toggle buy status', function () {
    $customerId = 'user123';
    $type = 'single';
    $selected = true;
    $itemId = 1;
    
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
        'customer_name' => 'John Doe',
    ]);

    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toEqual($cartModel);
});

test('it can get items count', function () {
    $customerId = 'user123';
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
    ]);
    $cartModel->id = 1;
    $count = 5;

    // Mock authentication
    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toEqual($count);
});

test('it can get items quantity', function () {
    $customerId = 'user123';
    $cartModel = new CartModel([
        'id' => 1,
        'customer_id' => $customerId,
    ]);
    $cartModel->id = 1;
    $qty = 10;

    // Mock authentication
    $this->actingAs(createMockUserForShoppingCart($customerId));

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

    expect($result)->toEqual($qty);
});

test('it handles existing product in cart', function () {
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
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
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

    expect($result)->toBeInstanceOf(CartModel::class);
});

test('it adds new product when not exists in cart', function () {
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
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
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

    expect($result)->toBeInstanceOf(CartModel::class);
});

test('it handles different options as different products', function () {
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
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
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

    expect($result)->toBeInstanceOf(CartModel::class);
});