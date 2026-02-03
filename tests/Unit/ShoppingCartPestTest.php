<?php

use MobileNowGroup\ShoppingCart\ShoppingCart;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use Illuminate\Foundation\Auth\User as Authenticatable;

beforeEach(function () {
    $this->mockStorage = Mockery::mock(CartStorageInterface::class);
    $this->cart = new ShoppingCart($this->mockStorage);
});

afterEach(function () {
    Mockery::close();
});



// Helper function to create mock user for shopping cart
function createMockUserForShoppingCart($customerId) {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($customerId);
    $user->shouldReceive('getAttribute')->with('name')->andReturn('Test User');
    $user->shouldReceive('setAttribute')->with('name', 'Test User')->andReturnSelf();
    $user->shouldReceive('offsetExists')->andReturn(true);
    $user->shouldReceive('offsetGet')->with('name')->andReturn('Test User');
    $user->name = 'Test User';
    return $user;
}

// Helper function to create mock cart model
function createMockCartModel($id, $customerId) {
    $cartModel = Mockery::mock(CartModel::class);
    $cartModel->shouldReceive('getAttribute')->with('id')->andReturn($id);
    $cartModel->shouldReceive('getAttribute')->with('customer_id')->andReturn($customerId);
    $cartModel->shouldReceive('getAttribute')->with('items')->andReturn(collect());
    $cartModel->shouldReceive('setAttribute')->andReturnSelf();
    $cartModel->shouldReceive('__get')->with('id')->andReturn($id);
    $cartModel->shouldReceive('__set')->andReturnSelf();
    $cartModel->id = $id;
    return $cartModel;
}

// Helper function to create mock cartable item
function createMockCartableItem($key, $price = 10.00, $sku = 'TEST-SKU', $name = 'Test Product', $weight = 1.0) {
    $mockItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Contracts\\Cartable');
    $mockItem->shouldReceive('getPrice')->andReturn($price);
    $mockItem->shouldReceive('getSku')->andReturn($sku);
    $mockItem->shouldReceive('getName')->andReturn($name);
    $mockItem->shouldReceive('getWeight')->andReturn($weight);
    $mockItem->shouldReceive('getKey')->andReturn($key);
    return $mockItem;
}

test('可以获取客户的购物车', function () {
    $customerId = 'user123';
    $cartModel = createMockCartModel(1, $customerId);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId, false)
        ->andReturn($cartModel);
    
    $result = $this->cart->getCart(false);
    
    expect($result)->toBe($cartModel);
});

test('可以添加商品到购物车', function () {
    $customerId = 'user123';
    $quantity = 2;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockItem = createMockCartableItem(1);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('addCartItem')
        ->once()
        ->with(1, Mockery::type('array'))
        ->andReturn(Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem'));
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->andReturn(null);
    
    $result = $this->cart->addProduct($mockItem, $quantity);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('可以添加带默认选项的商品', function () {
    $customerId = 'user123';
    $quantity = 1;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockItem = createMockCartableItem(1, 10.00, 'TEST-SKU-2');
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('addCartItem')
        ->once()
        ->with(1, Mockery::type('array'))
        ->andReturn(Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem'));
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->andReturn(null);
    
    $result = $this->cart->addProduct($mockItem, $quantity, false);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('可以从购物车移除商品', function () {
    $customerId = 'user123';
    $itemId = 1;
    
    $cartModel = createMockCartModel(1, $customerId);
    
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
    
    expect($result)->toBe(true);
});

test('可以更新购物车中的商品', function () {
    $customerId = 'user123';
    $itemId = 1;
    $quantity = 3;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockCartItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem');
    $mockCartItem->shouldReceive('getAttribute')->andReturn(null);
    $mockCartItem->shouldReceive('__get')->andReturn(null);
    $mockCartItem->shouldReceive('setAttribute')->andReturn(null);
    $mockCartItem->shouldReceive('__set')->andReturn(null);
    $mockCartItem->latest_price = 10.00;
    $mockCartItem->weight = 1.0;
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->once()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->twice()
        ->with($itemId)
        ->andReturn($mockCartItem);
    
    $this->mockStorage
        ->shouldReceive('updateCartItem')
        ->once()
        ->with($itemId, Mockery::type('array'))
        ->andReturn(true);
    
    $result = $this->cart->updateItems($itemId, $quantity);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('可以清空购物车', function () {
    $customerId = 'user123';
    $cartModel = createMockCartModel(1, $customerId);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('emptyCart')
        ->once()
        ->with(1)
        ->andReturn(true);
    
    $result = $this->cart->emptyCart();
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('可以切换购买状态', function () {
    $customerId = 'user123';
    $type = 'single';
    $selected = true;
    $itemId = 1;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockCartItem = Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem');
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->once()
        ->with($itemId)
        ->andReturn($mockCartItem);
        
    $this->mockStorage
        ->shouldReceive('updateCartItem')
        ->once()
        ->with($itemId, ['selected' => $selected])
        ->andReturn(true);
    
    $result = $this->cart->setCartItemSelection($type, $selected, $itemId);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('可以获取商品数量', function () {
    $customerId = 'user123';
    $cartModel = createMockCartModel(1, $customerId);
    $count = 5;
    
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
    
    expect($result)->toBe($count);
});

test('可以获取商品总量', function () {
    $customerId = 'user123';
    $cartModel = createMockCartModel(1, $customerId);
    $qty = 10;
    
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
    
    expect($result)->toBe($qty);
});

test('处理购物车中已存在的商品', function () {
    $customerId = 'user123';
    $quantity = 2;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockItem = createMockCartableItem(1);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('addCartItem')
        ->once()
        ->with(1, Mockery::type('array'))
        ->andReturn(Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem'));
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->andReturn(null);
    
    $result = $this->cart->addProduct($mockItem, $quantity);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('添加不存在于购物车的新商品', function () {
    $customerId = 'user123';
    $quantity = 2;
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockItem = createMockCartableItem(2, 10.00, 'TEST-SKU-2', 'Test Product 2', 1.5);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('addCartItem')
        ->once()
        ->with(1, Mockery::type('array'))
        ->andReturn(Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem'));
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->andReturn(null);
    
    $result = $this->cart->addProduct($mockItem, $quantity);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});

test('处理不同选项作为不同商品', function () {
    $customerId = 'user123';
    $quantity = 2;
    $options = ['color' => 'red', 'size' => 'large'];
    
    $cartModel = createMockCartModel(1, $customerId);
    $mockItem = createMockCartableItem(3, 10.00, 'TEST-SKU-3', 'Test Product with Options', 2.0);
    
    $this->actingAs(createMockUserForShoppingCart($customerId));
    
    $this->mockStorage
        ->shouldReceive('getCart')
        ->twice()
        ->with($customerId, false)
        ->andReturn($cartModel);
        
    $this->mockStorage
        ->shouldReceive('addCartItem')
        ->once()
        ->with(1, Mockery::type('array'))
        ->andReturn(Mockery::mock('MobileNowGroup\\ShoppingCart\\Models\\ShoppingCartItem'));
        
    $this->mockStorage
        ->shouldReceive('getCartItem')
        ->andReturn(null);
    
    $result = $this->cart->addProduct($mockItem, $quantity, false);
    
    expect($result)->toBeInstanceOf(CartModel::class);
});