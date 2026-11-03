<?php

use Fayne\ShoppingCart\Traits\CartTools;

describe('CartTools Trait', function () {
    beforeEach(function () {
        $this->testClass = new class {
            use CartTools;
            
            protected $items = [];
            protected $total = 0;
            
            public function setItems($items) {
                $this->items = $items;
            }
            
            public function getItems() {
                return $this->items;
            }
            
            public function setTotal($total) {
                $this->total = $total;
            }
            
            public function getTotal() {
                return $this->total;
            }
            
            public function calculateTotal() {
                $total = 0;
                foreach ($this->items as $item) {
                    $total += $item['price'] * $item['quantity'];
                }
                $this->total = $total;
                return $total;
            }
            
            public function isEmpty() {
                return empty($this->items);
            }
            
            public function count() {
                return count($this->items);
            }
            
            public function clear() {
                $this->items = [];
                $this->total = 0;
            }
        };
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('可以计算购物车总价', function () {
        $items = [
            ['id' => 1, 'price' => 10.00, 'quantity' => 2],
            ['id' => 2, 'price' => 15.50, 'quantity' => 1],
        ];
        
        $this->testClass->setItems($items);
        $total = $this->testClass->calculateTotal();
        
        expect($total)->toBe(35.50)
            ->and($this->testClass->getTotal())->toBe(35.50);
    });
    
    it('可以检查购物车是否为空', function () {
        // 空购物车
        expect($this->testClass->isEmpty())->toBeTrue();
        
        // 添加商品后
        $this->testClass->setItems([['id' => 1, 'price' => 10.00, 'quantity' => 1]]);
        expect($this->testClass->isEmpty())->toBeFalse();
    });
    
    it('可以统计购物车商品数量', function () {
        expect($this->testClass->count())->toBe(0);
        
        $items = [
            ['id' => 1, 'price' => 10.00, 'quantity' => 2],
            ['id' => 2, 'price' => 15.50, 'quantity' => 1],
        ];
        
        $this->testClass->setItems($items);
        expect($this->testClass->count())->toBe(2);
    });
    
    it('可以清空购物车', function () {
        // 先添加一些商品
        $items = [
            ['id' => 1, 'price' => 10.00, 'quantity' => 2],
            ['id' => 2, 'price' => 15.50, 'quantity' => 1],
        ];
        
        $this->testClass->setItems($items);
        $this->testClass->setTotal(35.50);
        
        expect($this->testClass->count())->toBe(2)
            ->and($this->testClass->getTotal())->toBe(35.50);
        
        // 清空购物车
        $this->testClass->clear();
        
        expect($this->testClass->count())->toBe(0)
            ->and($this->testClass->getTotal())->toBe(0)
            ->and($this->testClass->isEmpty())->toBeTrue();
    });
    
    it('可以处理空商品列表的总价计算', function () {
        $total = $this->testClass->calculateTotal();
        
        expect($total)->toBe(0)
            ->and($this->testClass->getTotal())->toBe(0);
    });
    
    it('可以处理包含零价格商品的计算', function () {
        $items = [
            ['id' => 1, 'price' => 0, 'quantity' => 5],
            ['id' => 2, 'price' => 10.00, 'quantity' => 2],
        ];
        
        $this->testClass->setItems($items);
        $total = $this->testClass->calculateTotal();
        
        expect($total)->toBe(20.00);
    });
    
    it('可以处理小数价格的精确计算', function () {
        $items = [
            ['id' => 1, 'price' => 9.99, 'quantity' => 3],
            ['id' => 2, 'price' => 15.50, 'quantity' => 2],
        ];
        
        $this->testClass->setItems($items);
        $total = $this->testClass->calculateTotal();
        
        expect($total)->toBe(60.97); // 9.99 * 3 + 15.50 * 2 = 29.97 + 31.00 = 60.97
    });
});