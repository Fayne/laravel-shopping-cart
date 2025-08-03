<?php

use MobileNowGroup\ShoppingCart\Events\ShopCart\CartApplyCouponAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartApplyCouponBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartEmptyAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartEmptyBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemAddAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemAddBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemIsBuyNow;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemRemoveAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemRemoveBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemUpdateAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartItemUpdateBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartRemoveCouponAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartRemoveCouponBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartShowAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\CartShowBefore;


describe('ShopCart Events', function () {
    afterEach(function () {
        Mockery::close();
    });
    
    describe('CartApplyCoupon Events', function () {
        it('可以创建CartApplyCouponBefore事件', function () {
            $couponCode = 'SAVE20';
            $cartId = 1;
            $event = new CartApplyCouponBefore($couponCode, $cartId);
            
            expect($event)->toBeInstanceOf(CartApplyCouponBefore::class);
            
            if (property_exists($event, 'couponCode')) {
                expect($event->couponCode)->toBe($couponCode);
            }
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartApplyCouponAfter事件', function () {
            $couponCode = 'SAVE20';
            $cartId = 1;
            $result = ['success' => true, 'discount' => 20.0];
            $event = new CartApplyCouponAfter($couponCode, $cartId, $result);
            
            expect($event)->toBeInstanceOf(CartApplyCouponAfter::class);
            
            if (property_exists($event, 'couponCode')) {
                expect($event->couponCode)->toBe($couponCode);
            }
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
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
    
    describe('CartItemAdd Events', function () {
        it('可以创建CartItemAddBefore事件', function () {
            $itemData = ['product_id' => 1, 'quantity' => 2, 'price' => 10.99];
            $event = new CartItemAddBefore($itemData);
            
            expect($event)->toBeInstanceOf(CartItemAddBefore::class);
            
            if (property_exists($event, 'itemData')) {
                expect($event->itemData)->toBe($itemData);
            }
        });
        
        it('可以创建CartItemAddAfter事件', function () {
            $itemData = ['product_id' => 1, 'quantity' => 2, 'price' => 10.99];
            $result = ['success' => true, 'item_id' => 123];
            $event = new CartItemAddAfter($itemData, $result);
            
            expect($event)->toBeInstanceOf(CartItemAddAfter::class);
            
            if (property_exists($event, 'itemData')) {
                expect($event->itemData)->toBe($itemData);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartItemIsBuyNow Event', function () {
        it('可以创建CartItemIsBuyNow事件', function () {
            $itemId = 123;
            $isBuyNow = true;
            $event = new CartItemIsBuyNow($itemId, $isBuyNow);
            
            expect($event)->toBeInstanceOf(CartItemIsBuyNow::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'isBuyNow')) {
                expect($event->isBuyNow)->toBe($isBuyNow);
            }
        });
    });
    
    describe('CartItemRemove Events', function () {
        it('可以创建CartItemRemoveBefore事件', function () {
            $itemId = 456;
            $event = new CartItemRemoveBefore($itemId);
            
            expect($event)->toBeInstanceOf(CartItemRemoveBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
        });
        
        it('可以创建CartItemRemoveAfter事件', function () {
            $itemId = 456;
            $result = true;
            $event = new CartItemRemoveAfter($itemId, $result);
            
            expect($event)->toBeInstanceOf(CartItemRemoveAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartItemUpdate Events', function () {
        it('可以创建CartItemUpdateBefore事件', function () {
            $itemId = 789;
            $updateData = ['quantity' => 3, 'price' => 15.99];
            $event = new CartItemUpdateBefore($itemId, $updateData);
            
            expect($event)->toBeInstanceOf(CartItemUpdateBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'updateData')) {
                expect($event->updateData)->toBe($updateData);
            }
        });
        
        it('可以创建CartItemUpdateAfter事件', function () {
            $itemId = 789;
            $updateData = ['quantity' => 3, 'price' => 15.99];
            $result = ['success' => true, 'updated_item' => []];
            $event = new CartItemUpdateAfter($itemId, $updateData, $result);
            
            expect($event)->toBeInstanceOf(CartItemUpdateAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'updateData')) {
                expect($event->updateData)->toBe($updateData);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartRemoveCoupon Events', function () {
        it('可以创建CartRemoveCouponBefore事件', function () {
            $couponCode = 'SAVE20';
            $cartId = 1;
            $event = new CartRemoveCouponBefore($couponCode, $cartId);
            
            expect($event)->toBeInstanceOf(CartRemoveCouponBefore::class);
            
            if (property_exists($event, 'couponCode')) {
                expect($event->couponCode)->toBe($couponCode);
            }
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartRemoveCouponAfter事件', function () {
            $couponCode = 'SAVE20';
            $cartId = 1;
            $result = ['success' => true];
            $event = new CartRemoveCouponAfter($couponCode, $cartId, $result);
            
            expect($event)->toBeInstanceOf(CartRemoveCouponAfter::class);
            
            if (property_exists($event, 'couponCode')) {
                expect($event->couponCode)->toBe($couponCode);
            }
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
            if (property_exists($event, 'result')) {
                expect($event->result)->toBe($result);
            }
        });
    });
    
    describe('CartShow Events', function () {
        it('可以创建CartShowBefore事件', function () {
            $cartId = 1;
            $event = new CartShowBefore($cartId);
            
            expect($event)->toBeInstanceOf(CartShowBefore::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartShowAfter事件', function () {
            $cartId = 1;
            $cartData = ['id' => 1, 'items' => [], 'total' => 0];
            $event = new CartShowAfter($cartId, $cartData);
            
            expect($event)->toBeInstanceOf(CartShowAfter::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
            if (property_exists($event, 'cartData')) {
                expect($event->cartData)->toBe($cartData);
            }
        });
    });
    
    it('所有ShopCart事件都可以被序列化', function () {
        $events = [
            new CartApplyCouponBefore('SAVE20', 1),
            new CartApplyCouponAfter('SAVE20', 1, ['success' => true]),
            new CartEmptyBefore(1),
            new CartEmptyAfter(1, true),
            new CartItemAddBefore(['product_id' => 1]),
            new CartItemAddAfter(['product_id' => 1], ['success' => true]),
            new CartItemIsBuyNow(123, true),
            new CartItemRemoveBefore(456),
            new CartItemRemoveAfter(456, true),
            new CartItemUpdateBefore(789, ['quantity' => 3]),
            new CartItemUpdateAfter(789, ['quantity' => 3], ['success' => true]),
            new CartRemoveCouponBefore('SAVE20', 1),
            new CartRemoveCouponAfter('SAVE20', 1, ['success' => true]),
            new CartShowBefore(1),
            new CartShowAfter(1, ['cart' => 'data'])
        ];
        
        foreach ($events as $event) {
            $serialized = serialize($event);
            $unserialized = unserialize($serialized);
            
            expect($unserialized)->toBeInstanceOf(get_class($event));
        }
    });
});