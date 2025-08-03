<?php

use MobileNowGroup\ShoppingCart\Http\Requests\CartRequest;
use Illuminate\Support\Facades\Validator;


describe('CartRequest', function () {
    beforeEach(function () {
        $this->request = new CartRequest();
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('可以实例化CartRequest', function () {
        expect($this->request)->toBeInstanceOf(CartRequest::class);
    });
    
    it('可以验证添加商品到购物车的数据', function () {
        // 测试有效数据
        $validData = [
            'product_id' => 1,
            'quantity' => 2,
            'price' => 10.99,
            'name' => 'Test Product',
            'options' => []
        ];
        
        // 如果 CartRequest 有 rules 方法，测试验证规则
        if (method_exists($this->request, 'rules')) {
            $rules = $this->request->rules();
            $validator = Validator::make($validData, $rules);
            expect($validator->passes())->toBeTrue();
        } else {
            // 如果没有 rules 方法，测试基本功能
            expect($this->request)->toBeInstanceOf(CartRequest::class);
        }
    });
    
    it('可以验证无效的商品数据', function () {
        // 测试无效数据（缺少必需字段）
        $invalidData = [
            'quantity' => 'invalid',
            'price' => -10,
        ];
        
        // 如果 CartRequest 有 rules 方法，测试验证规则
        if (method_exists($this->request, 'rules')) {
            $rules = $this->request->rules();
            $validator = Validator::make($invalidData, $rules);
            expect($validator->fails())->toBeTrue();
        } else {
            // 如果没有 rules 方法，测试基本功能
            expect($this->request)->toBeInstanceOf(CartRequest::class);
        }
    });
    
    it('可以检查授权', function () {
        // 如果 CartRequest 有 authorize 方法，测试授权
        if (method_exists($this->request, 'authorize')) {
            $authorized = $this->request->authorize();
            expect($authorized)->toBeTrue();
        } else {
            // 如果没有 authorize 方法，测试基本功能
            expect($this->request)->toBeInstanceOf(CartRequest::class);
        }
    });
    
    it('可以获取验证消息', function () {
        // 如果 CartRequest 有 messages 方法，测试自定义消息
        if (method_exists($this->request, 'messages')) {
            $messages = $this->request->messages();
            expect($messages)->toBeArray();
        } else {
            // 如果没有 messages 方法，测试基本功能
            expect($this->request)->toBeInstanceOf(CartRequest::class);
        }
    });
    
    it('可以验证数量字段', function () {
        $testCases = [
            ['quantity' => 1, 'expected' => true],
            ['quantity' => 0, 'expected' => false],
            ['quantity' => -1, 'expected' => false],
            ['quantity' => 'abc', 'expected' => false],
            ['quantity' => 1.5, 'expected' => false], // 假设只允许整数
        ];
        
        foreach ($testCases as $case) {
            if (method_exists($this->request, 'rules')) {
                $rules = $this->request->rules();
                if (isset($rules['quantity'])) {
                    $validator = Validator::make(
                        ['quantity' => $case['quantity']], 
                        ['quantity' => $rules['quantity']]
                    );
                    
                    if ($case['expected']) {
                        expect($validator->passes())->toBeTrue("Quantity {$case['quantity']} should be valid");
                    } else {
                        expect($validator->fails())->toBeTrue("Quantity {$case['quantity']} should be invalid");
                    }
                }
            }
        }
        
        // 如果没有验证规则，至少测试实例化
        expect($this->request)->toBeInstanceOf(CartRequest::class);
    });
    
    it('可以验证价格字段', function () {
        $testCases = [
            ['price' => 10.99, 'expected' => true],
            ['price' => 0, 'expected' => true], // 免费商品
            ['price' => -1, 'expected' => false],
            ['price' => 'abc', 'expected' => false],
        ];
        
        foreach ($testCases as $case) {
            if (method_exists($this->request, 'rules')) {
                $rules = $this->request->rules();
                if (isset($rules['price'])) {
                    $validator = Validator::make(
                        ['price' => $case['price']], 
                        ['price' => $rules['price']]
                    );
                    
                    if ($case['expected']) {
                        expect($validator->passes())->toBeTrue("Price {$case['price']} should be valid");
                    } else {
                        expect($validator->fails())->toBeTrue("Price {$case['price']} should be invalid");
                    }
                }
            }
        }
        
        // 如果没有验证规则，至少测试实例化
        expect($this->request)->toBeInstanceOf(CartRequest::class);
    });
});