<?php

use Fayne\ShoppingCart\Events\ShopCart\CartApplyCouponAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartApplyCouponBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartEmptyAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartEmptyBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemAddAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemAddBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemIsBuyNow;
use Fayne\ShoppingCart\Events\ShopCart\CartItemRemoveAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemRemoveBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartItemUpdateAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartItemUpdateBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartRemoveCouponAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartRemoveCouponBefore;
use Fayne\ShoppingCart\Events\ShopCart\CartShowAfter;
use Fayne\ShoppingCart\Events\ShopCart\CartShowBefore;


describe('ShopCart Events', function () {
    afterEach(function () {
        Mockery::close();
    });
    
    describe('CartApplyCoupon Events', function () {
        it('可以创建CartApplyCouponBefore事件', function () {
            $couponCode = 'SAVE20';
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartApplyCouponBefore($couponCode, $cart);
            
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
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartApplyCouponAfter($couponCode, $cart);
            
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
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartEmptyBefore($cart);
            
            expect($event)->toBeInstanceOf(CartEmptyBefore::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartEmptyAfter事件', function () {
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartEmptyAfter($cart);
            
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
            $isBuyNow = false;
            $productSkuId = 1;
            $quantity = 2;
            $event = new CartItemAddBefore($isBuyNow, $productSkuId, $quantity);
            
            expect($event)->toBeInstanceOf(CartItemAddBefore::class);
            
            if (property_exists($event, 'itemData')) {
                expect($event->itemData)->toBe($itemData);
            }
        });
        
        it('可以创建CartItemAddAfter事件', function () {
            $isBuyNow = false;
            $productSkuId = 1;
            $quantity = 2;
            $event = new CartItemAddAfter($isBuyNow, $productSkuId, $quantity);
            
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
            $isBuyNow = true;
            $productSkuId = 123;
            $quantity = 1;
            $event = new CartItemIsBuyNow($isBuyNow, $productSkuId, $quantity);
            
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
            $event = new CartItemRemoveAfter($itemId);
            
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
            $quantity = 3;
            $event = new CartItemUpdateBefore($itemId, $quantity);
            
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
            $quantity = 3;
            $event = new CartItemUpdateAfter($itemId, $quantity);
            
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
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartRemoveCouponBefore($couponCode, $cart);
            
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
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartRemoveCouponAfter($couponCode, $cart);
            
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
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartShowBefore($cart);
            
            expect($event)->toBeInstanceOf(CartShowBefore::class);
            
            if (property_exists($event, 'cartId')) {
                expect($event->cartId)->toBe($cartId);
            }
        });
        
        it('可以创建CartShowAfter事件', function () {
            $cart = null; // 或者创建一个 ShoppingCart 实例
            $event = new CartShowAfter($cart);
            
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
            new CartApplyCouponBefore('SAVE20', null),
            new CartApplyCouponAfter('SAVE20', null),
            new CartEmptyBefore(null),
            new CartEmptyAfter(null),
            new CartItemAddBefore(false, 1, 1),
            new CartItemAddAfter(false, 1, 1),
            new CartItemIsBuyNow(true, 123, 1),
            new CartItemRemoveBefore(456),
            new CartItemRemoveAfter(456),
            new CartItemUpdateBefore(789, 3),
            new CartItemUpdateAfter(789, 3),
            new CartRemoveCouponBefore('SAVE20', null),
            new CartRemoveCouponAfter('SAVE20', null),
            new CartShowBefore(null),
            new CartShowAfter(null)
        ];
        
        foreach ($events as $event) {
            $serialized = serialize($event);
            $unserialized = unserialize($serialized);
            
            expect($unserialized)->toBeInstanceOf(get_class($event));
        }
    });
});