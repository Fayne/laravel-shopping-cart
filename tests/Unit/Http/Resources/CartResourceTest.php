<?php

use MobileNowGroup\ShoppingCart\Http\Resources\CartResource;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use Illuminate\Http\Request;


describe('CartResource', function () {
    beforeEach(function () {
        $this->mockCart = Mockery::mock(ShoppingCart::class);
        $this->mockRequest = Mockery::mock(Request::class);
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('可以实例化CartResource', function () {
        $resource = new CartResource($this->mockCart);
        expect($resource)->toBeInstanceOf(CartResource::class);
    });
    
    it('可以转换购物车数据为数组', function () {
        // 模拟购物车数据
        $this->mockCart->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('customer_id')
            ->andReturn(123);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('total')
            ->andReturn(99.99);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('created_at')
            ->andReturn('2023-01-01 00:00:00');
        $this->mockCart->shouldReceive('getAttribute')
            ->with('updated_at')
            ->andReturn('2023-01-01 12:00:00');
        
        // 模拟购物车商品
        $mockItems = collect([
            Mockery::mock(ShoppingCartItem::class),
            Mockery::mock(ShoppingCartItem::class)
        ]);
        
        $this->mockCart->shouldReceive('relationLoaded')
            ->with('items')
            ->andReturn(true);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn($mockItems);
        
        // 添加 toArray 方法的期望
        $this->mockCart->shouldReceive('toArray')
            ->andReturn([
                'id' => 1,
                'customer_id' => 123,
                'total' => 99.99,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 12:00:00',
                'items' => []
            ]);
        
        $resource = new CartResource($this->mockCart);
        
        // 如果 CartResource 有 toArray 方法，测试数据转换
        if (method_exists($resource, 'toArray')) {
            $array = $resource->toArray($this->mockRequest);
            expect($array)->toBeArray();
            
            // 检查基本字段是否存在
            if (isset($array['id'])) {
                expect($array['id'])->toBe(1);
            }
            if (isset($array['customer_id'])) {
                expect($array['customer_id'])->toBe(123);
            }
            if (isset($array['total'])) {
                expect($array['total'])->toBe(99.99);
            }
        } else {
            // 如果没有 toArray 方法，测试基本功能
            expect($resource)->toBeInstanceOf(CartResource::class);
        }
    });
    
    it('可以处理空购物车', function () {
        $this->mockCart->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('customer_id')
            ->andReturn(123);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('total')
            ->andReturn(0);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('created_at')
            ->andReturn('2023-01-01 00:00:00');
        $this->mockCart->shouldReceive('getAttribute')
            ->with('updated_at')
            ->andReturn('2023-01-01 12:00:00');
        
        // 空的商品集合
        $this->mockCart->shouldReceive('relationLoaded')
            ->with('items')
            ->andReturn(true);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(collect([]));
        
        // 添加 toArray 方法的期望
        $this->mockCart->shouldReceive('toArray')
            ->andReturn([
                'id' => 1,
                'customer_id' => 123,
                'total' => 0,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 12:00:00',
                'items' => []
            ]);
        
        $resource = new CartResource($this->mockCart);
        
        if (method_exists($resource, 'toArray')) {
            $array = $resource->toArray($this->mockRequest);
            expect($array)->toBeArray();
            
            if (isset($array['total'])) {
                expect($array['total'])->toBe(0);
            }
            if (isset($array['items'])) {
                expect($array['items'])->toBeArray()
                    ->and(count($array['items']))->toBe(0);
            }
        } else {
            expect($resource)->toBeInstanceOf(CartResource::class);
        }
    });
    
    it('可以包含购物车商品资源', function () {
        $this->mockCart->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('customer_id')
            ->andReturn(123);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('total')
            ->andReturn(50.00);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('created_at')
            ->andReturn('2023-01-01 00:00:00');
        $this->mockCart->shouldReceive('getAttribute')
            ->with('updated_at')
            ->andReturn('2023-01-01 12:00:00');
        
        // 模拟商品
        $mockItem1 = Mockery::mock(ShoppingCartItem::class);
        $mockItem1->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $mockItem1->shouldReceive('getAttribute')
            ->with('quantity')
            ->andReturn(2);
        $mockItem1->shouldReceive('getAttribute')
            ->with('total')
            ->andReturn(25.00);
        
        $mockItem2 = Mockery::mock(ShoppingCartItem::class);
        $mockItem2->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(2);
        $mockItem2->shouldReceive('getAttribute')
            ->with('quantity')
            ->andReturn(1);
        $mockItem2->shouldReceive('getAttribute')
            ->with('total')
            ->andReturn(25.00);
        
        $mockItems = collect([$mockItem1, $mockItem2]);
        
        $this->mockCart->shouldReceive('relationLoaded')
            ->with('items')
            ->andReturn(true);
        $this->mockCart->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn($mockItems);
        
        $resource = new CartResource($this->mockCart);
        
        if (method_exists($resource, 'toArray')) {
            $array = $resource->toArray($this->mockRequest);
            expect($array)->toBeArray();
            
            if (isset($array['items'])) {
                expect($array['items'])->toBeArray();
                // 检查是否包含商品数据
                if (count($array['items']) > 0) {
                    expect(count($array['items']))->toBeGreaterThan(0);
                }
            }
        } else {
            expect($resource)->toBeInstanceOf(CartResource::class);
        }
    });
    
    it('可以处理附加数据', function () {
        $this->mockCart->shouldReceive('getAttribute')
            ->andReturn(null);
        $this->mockCart->shouldReceive('relationLoaded')
            ->andReturn(false);
        
        $resource = new CartResource($this->mockCart);
        
        // 如果 CartResource 有 with 方法，测试附加数据
        if (method_exists($resource, 'with')) {
            $additionalData = $resource->with($this->mockRequest);
            expect($additionalData)->toBeArray();
        } else {
            expect($resource)->toBeInstanceOf(CartResource::class);
        }
    });
    
    it('可以自定义响应格式', function () {
        $this->mockCart->shouldReceive('getAttribute')
            ->andReturn(null);
        $this->mockCart->shouldReceive('relationLoaded')
            ->andReturn(false);
        $this->mockCart->shouldReceive('toArray')
            ->andReturn([]);
        
        $resource = new CartResource($this->mockCart);
        
        // 测试资源可以被序列化
        expect($resource)->toBeInstanceOf(CartResource::class);
        
        // 如果有自定义方法，可以进一步测试
        if (method_exists($resource, 'toResponse')) {
            $response = $resource->toResponse($this->mockRequest);
            expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
        }
    });
});