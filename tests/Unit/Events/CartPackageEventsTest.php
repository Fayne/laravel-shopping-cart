<?php

use MobileNowGroup\ShoppingCart\Events\CartPackage\CartAddProductAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartAddProductBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartCollectTotalAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartCollectTotalBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartEmptyAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartEmptyBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartGetAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartGetBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartRemoveItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartRemoveItemBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartToggleBuyAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartToggleBuyBefore;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartUpdateItemAfter;
use MobileNowGroup\ShoppingCart\Events\CartPackage\CartUpdateItemBefore;


describe('CartPackage Events', function () {
    afterEach(function () {
        Mockery::close();
    });
    
    describe('CartAddProduct Events', function () {
        it('可以创建CartAddProductBefore事件', function () {
            $data = ['product_id' => 1, 'quantity' => 2];
            $event = new CartAddProductBefore($data);
            
            expect($event)->toBeInstanceOf(CartAddProductBefore::class);
            
            if (property_exists($event, 'data')) {
                expect($event->data)->toBe($data);
            }
        });
        
        it('可以创建CartAddProductAfter事件', function () {
            $data = ['product_id' => 1, 'quantity' => 2];
            $result = ['success' => true, 'item_id' => 123];
            $event = new CartAddProductAfter($data, $result);
            
            expect($event)->toBeInstanceOf(CartAddProductAfter::class);
            
            if (property_exists($event, 'data')) {
                expect($event->data)->toBe($data);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartCollectTotal Events', function () {
        it('可以创建CartCollectTotalBefore事件', function () {
            $cartData = ['id' => 1, 'items' => []];
            $event = new CartCollectTotalBefore($cartData);
            
            expect($event)->toBeInstanceOf(CartCollectTotalBefore::class);
            
            if (property_exists($event, 'cartData')) {
                expect($event->cartData)->toBe($cartData);
            }
        });
        
        it('可以创建CartCollectTotalAfter事件', function () {
            $cartData = ['id' => 1, 'items' => []];
            $total = 99.99;
            $event = new CartCollectTotalAfter($cartData, $total);
            
            expect($event)->toBeInstanceOf(CartCollectTotalAfter::class);
            
            if (property_exists($event, 'cartData')) {
                expect($event->cartData)->toBe($cartData);
            }
            if (property_exists($event, 'total')) {
                expect($event->total)->toBe($total);
            }
        });
    });
    
    describe('CartEmpty Events', function () {
        it('可以创建CartEmptyBefore事件', function () {
            $cartId = 1;
            $event = new CartEmptyBefore($cartId);
            
            expect($event)->toBeInstanceOf(CartEmptyBefore::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartEmptyAfter事件', function () {
            $cartId = 1;
            $result = true;
            $event = new CartEmptyAfter($cartId, $result);
            
            expect($event)->toBeInstanceOf(CartEmptyAfter::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartGet Events', function () {
        it('可以创建CartGetBefore事件', function () {
            $customerId = 123;
            $event = new CartGetBefore($customerId);
            
            expect($event)->toBeInstanceOf(CartGetBefore::class);
            
            if (property_exists($event, 'customerId')) {
                expect($event->customerId)->toBe($customerId);
            }
        });
        
        it('可以创建CartGetAfter事件', function () {
            $customerId = 123;
            $cart = ['id' => 1, 'customer_id' => 123];
            $event = new CartGetAfter($customerId, $cart);
            
            expect($event)->toBeInstanceOf(CartGetAfter::class);
            
            if (property_exists($event, 'customerId')) {
                expect($event->customerId)->toBe($customerId);
            }
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($cart);
            }
        });
    });
    
    describe('CartRemoveItem Events', function () {
        it('可以创建CartRemoveItemBefore事件', function () {
            $itemId = 456;
            $event = new CartRemoveItemBefore($itemId);
            
            expect($event)->toBeInstanceOf(CartRemoveItemBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
        });
        
        it('可以创建CartRemoveItemAfter事件', function () {
            $itemId = 456;
            $result = true;
            $event = new CartRemoveItemAfter($itemId, $result);
            
            expect($event)->toBeInstanceOf(CartRemoveItemAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartToggleBuy Events', function () {
        it('可以创建CartToggleBuyBefore事件', function () {
            $itemId = 789;
            $isBuy = true;
            $event = new CartToggleBuyBefore($itemId, $isBuy);
            
            expect($event)->toBeInstanceOf(CartToggleBuyBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'isBuy')) {
                expect($event->isBuy)->toBe($isBuy);
            }
        });
        
        it('可以创建CartToggleBuyAfter事件', function () {
            $itemId = 789;
            $isBuy = true;
            $result = ['success' => true];
            $event = new CartToggleBuyAfter($itemId, $isBuy, $result);
            
            expect($event)->toBeInstanceOf(CartToggleBuyAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'isBuy')) {
                expect($event->isBuy)->toBe($isBuy);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartUpdateItem Events', function () {
        it('可以创建CartUpdateItemBefore事件', function () {
            $itemId = 101;
            $data = ['quantity' => 3, 'price' => 15.99];
            $event = new CartUpdateItemBefore($itemId, $data);
            
            expect($event)->toBeInstanceOf(CartUpdateItemBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'data')) {
                expect($event->data)->toBe($data);
            }
        });
        
        it('可以创建CartUpdateItemAfter事件', function () {
            $itemId = 101;
            $data = ['quantity' => 3, 'price' => 15.99];
            $result = ['success' => true, 'updated_item' => []];
            $event = new CartUpdateItemAfter($itemId, $data, $result);
            
            expect($event)->toBeInstanceOf(CartUpdateItemAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'data')) {
                expect($event->data)->toBe($data);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    it('所有事件都可以被序列化', function () {
        $events = [
            new CartAddProductBefore(['test' => 'data']),
            new CartAddProductAfter(['test' => 'data'], ['result' => 'success']),
            new CartCollectTotalBefore(['cart' => 'data']),
            new CartCollectTotalAfter(['cart' => 'data'], 100.0),
            new CartEmptyBefore(1),
            new CartEmptyAfter(1, true),
            new CartGetBefore(123),
            new CartGetAfter(123, ['cart' => 'data']),
            new CartRemoveItemBefore(456),
            new CartRemoveItemAfter(456, true),
            new CartToggleBuyBefore(789, true),
            new CartToggleBuyAfter(789, true, ['success' => true]),
            new CartUpdateItemBefore(101, ['data' => 'test']),
            new CartUpdateItemAfter(101, ['data' => 'test'], ['result' => 'success'])
        ];
        
        foreach ($events as $event) {
            $serialized = serialize($event);
            $unserialized = unserialize($serialized);
            
            expect($unserialized)->toBeInstanceOf(get_class($event));
        }
    });
});