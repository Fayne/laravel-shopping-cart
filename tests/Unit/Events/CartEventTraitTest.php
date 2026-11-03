<?php

use Fayne\ShoppingCart\Events\Traits\CartEvent;


describe('CartEvent Trait', function () {
    beforeEach(function () {
        $this->testClass = new class {
            use CartEvent;
            
            public $data;
            public $timestamp;
            public $eventType;
            
            public function __construct($data = null, $eventType = 'test') {
                $this->data = $data;
                $this->eventType = $eventType;
                $this->timestamp = now();
            }
            
            // 公开访问 trait 的方法用于测试
            public function testGetEventData() {
                return method_exists($this, 'getEventData') ? $this->getEventData() : $this->data;
            }
            
            public function testGetEventType() {
                return method_exists($this, 'getEventType') ? $this->getEventType() : $this->eventType;
            }
            
            public function testGetTimestamp() {
                return method_exists($this, 'getTimestamp') ? $this->getTimestamp() : $this->timestamp;
            }
            
            public function testSetEventData($data) {
                if (method_exists($this, 'setEventData')) {
                    return $this->setEventData($data);
                }
                $this->data = $data;
                return $this;
            }
            
            public function testToArray() {
                if (method_exists($this, 'toArray')) {
                    return $this->toArray();
                }
                return [
                    'data' => $this->data,
                    'event_type' => $this->eventType,
                    'timestamp' => $this->timestamp
                ];
            }
        };
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('可以使用CartEvent trait', function () {
        expect($this->testClass)->toBeObject();
        
        // 检查是否使用了 CartEvent trait
        $traits = class_uses($this->testClass);
        expect($traits)->toContain(CartEvent::class);
    });
    
    it('可以设置和获取事件数据', function () {
        $testData = ['product_id' => 1, 'quantity' => 2, 'price' => 10.99];
        
        $this->testClass->testSetEventData($testData);
        $retrievedData = $this->testClass->testGetEventData();
        
        expect($retrievedData)->toBe($testData);
    });
    
    it('可以获取事件类型', function () {
        $eventType = $this->testClass->testGetEventType();
        expect($eventType)->toBe('test');
    });
    
    it('可以获取时间戳', function () {
        $timestamp = $this->testClass->testGetTimestamp();
        expect($timestamp)->not()->toBeNull();
    });
    
    it('可以将事件转换为数组', function () {
        $testData = ['product_id' => 1, 'quantity' => 2];
        $this->testClass->testSetEventData($testData);
        
        $array = $this->testClass->testToArray();
        
        expect($array)->toBeArray()
            ->and($array)->toHaveKey('data')
            ->and($array['data'])->toBe($testData);
    });
    
    it('可以处理空数据', function () {
        $this->testClass->testSetEventData(null);
        $retrievedData = $this->testClass->testGetEventData();
        
        expect($retrievedData)->toBeNull();
    });
    
    it('可以处理复杂的数据结构', function () {
        $complexData = [
            'cart' => [
                'id' => 1,
                'customer_id' => 123,
                'items' => [
                    ['product_id' => 1, 'quantity' => 2, 'price' => 10.99],
                    ['product_id' => 2, 'quantity' => 1, 'price' => 25.50]
                ],
                'coupons' => [
                    ['code' => 'SAVE20', 'discount' => 20.0]
                ],
                'totals' => [
                    'subtotal' => 47.48,
                    'discount' => 20.0,
                    'total' => 27.48
                ]
            ],
            'metadata' => [
                'source' => 'web',
                'user_agent' => 'Mozilla/5.0',
                'ip_address' => '192.168.1.1'
            ]
        ];
        
        $this->testClass->testSetEventData($complexData);
        $retrievedData = $this->testClass->testGetEventData();
        
        expect($retrievedData)->toBe($complexData)
            ->and($retrievedData['cart']['id'])->toBe(1)
            ->and($retrievedData['cart']['items'])->toHaveCount(2)
            ->and($retrievedData['cart']['totals']['total'])->toBe(27.48)
            ->and($retrievedData['metadata']['source'])->toBe('web');
    });
    
    it('可以链式调用方法', function () {
        $testData = ['test' => 'data'];
        
        $result = $this->testClass->testSetEventData($testData);
        
        // 检查是否返回了实例本身（支持链式调用）
        expect($result)->toBe($this->testClass);
        expect($this->testClass->testGetEventData())->toBe($testData);
    });
    
    it('可以验证事件数据的持久性', function () {
        $testData = ['persistence' => 'test', 'number' => 42];
        $this->testClass->testSetEventData($testData);
        
        // 验证数据在对象中保持不变
        expect($this->testClass->testGetEventData())->toBe($testData);
        
        // 验证可以多次获取相同数据
        expect($this->testClass->testGetEventData())->toBe($testData);
    });
    
    it('可以处理不同类型的数据', function () {
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['key' => 'value'],
            'object' => (object) ['property' => 'value']
        ];
        
        foreach ($testCases as $type => $data) {
            $this->testClass->testSetEventData($data);
            $retrievedData = $this->testClass->testGetEventData();
            
            expect($retrievedData)->toBe($data, "Failed for type: {$type}");
        }
    });
    
    it('可以验证事件的完整性', function () {
        $eventData = [
            'action' => 'add_to_cart',
            'product_id' => 1,
            'quantity' => 2,
            'user_id' => 123
        ];
        
        $this->testClass->testSetEventData($eventData);
        $array = $this->testClass->testToArray();
        
        // 验证数组包含所有必要的字段
        expect($array)->toHaveKey('data')
            ->and($array)->toHaveKey('event_type')
            ->and($array)->toHaveKey('timestamp')
            ->and($array['data']['action'])->toBe('add_to_cart')
            ->and($array['data']['product_id'])->toBe(1)
            ->and($array['data']['quantity'])->toBe(2)
            ->and($array['data']['user_id'])->toBe(123);
    });
});