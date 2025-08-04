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

// 辅助函数
function createMockUser($id = 1)
{
    return new class($id) implements \Illuminate\Contracts\Auth\Authenticatable {
        private $id;
        
        public function __construct($id)
        {
            $this->id = $id;
        }
        
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return $this->id; }
        public function getAuthPassword() { return 'password'; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return 'token'; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
    };
}

describe('CartPackage Events', function () {
    afterEach(function () {
        Mockery::close();
    });
    
    describe('CartAddProduct Events', function () {
        it('可以创建CartAddProductBefore事件', function () {
            $mockCartable = Mockery::mock(\MobileNowGroup\ShoppingCart\Contracts\Cartable::class);
            $quantity = 2;
            $isBuyNow = false;
            $event = new CartAddProductBefore($mockCartable, $quantity, $isBuyNow);
            
            expect($event)->toBeInstanceOf(CartAddProductBefore::class);
            
            if (property_exists($event, 'item')) {
                expect($event->item)->toBe($mockCartable);
            }
            if (property_exists($event, 'quantity')) {
                expect($event->quantity)->toBe($quantity);
            }
        });
        
        it('可以创建CartAddProductAfter事件', function () {
            $mockCartable = Mockery::mock(\MobileNowGroup\ShoppingCart\Contracts\Cartable::class);
            $event = new CartAddProductAfter($mockCartable);
            
            expect($event)->toBeInstanceOf(CartAddProductAfter::class);
            
            if (property_exists($event, 'item')) {
                expect($event->item)->toBe($mockCartable);
            }
        });
    });
    
    describe('CartCollectTotal Events', function () {
        it('可以创建CartCollectTotalBefore事件', function () {
            $mockCart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
            $event = new CartCollectTotalBefore($mockCart);
            
            expect($event)->toBeInstanceOf(CartCollectTotalBefore::class);
            
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($mockCart);
            }
        });
        
        it('可以创建CartCollectTotalAfter事件', function () {
            $mockCart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
            $event = new CartCollectTotalAfter($mockCart);
            
            expect($event)->toBeInstanceOf(CartCollectTotalAfter::class);
            
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($mockCart);
            }
        });
    });
    
    describe('CartEmpty Events', function () {
        it('可以创建CartEmptyBefore事件', function () {
            $mockCart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
            $event = new CartEmptyBefore($mockCart);
            
            expect($event)->toBeInstanceOf(CartEmptyBefore::class);
            
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($mockCart);
            }
        });
        
        it('可以创建CartEmptyAfter事件', function () {
            $mockCart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
            $event = new CartEmptyAfter($mockCart);
            
            expect($event)->toBeInstanceOf(CartEmptyAfter::class);
            
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($mockCart);
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
            $cart = new \MobileNowGroup\ShoppingCart\Models\ShoppingCart([
                'id' => 1,
                'customer_id' => 123
            ]);
            $user = createMockUser(123);
            $event = new CartGetAfter($cart, $user);
            
            expect($event)->toBeInstanceOf(CartGetAfter::class);
            
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($cart);
            }
            if (property_exists($event, 'user')) {
                expect($event->user)->toBe($user);
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
            $cart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
            $event = new CartRemoveItemAfter($itemId, $cart);
            
            expect($event)->toBeInstanceOf(CartRemoveItemAfter::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'cart')) {
                expect($event->cart)->toBe($cart);
            }
        });
    });
    
    describe('CartToggleBuy Events', function () {
        it('可以创建CartToggleBuyBefore事件', function () {
            $type = 'item';
            $selected = true;
            $itemId = 789;
            $event = new CartToggleBuyBefore($type, $selected, $itemId);
            
            expect($event)->toBeInstanceOf(CartToggleBuyBefore::class);
            
            if (property_exists($event, 'type')) {
                expect($event->type)->toBe($type);
            }
            if (property_exists($event, 'selected')) {
                expect($event->selected)->toBe($selected);
            }
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
        });
        
        it('可以创建CartToggleBuyAfter事件', function () {
            $type = 'buy';
            $selected = true;
            $itemId = 789;
            $event = new CartToggleBuyAfter($type, $selected, $itemId);
            
            expect($event)->toBeInstanceOf(CartToggleBuyAfter::class);
            
            if (property_exists($event, 'type')) {
                expect($event->type)->toBe($type);
            }
            if (property_exists($event, 'selected')) {
                expect($event->selected)->toBe($selected);
            }
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
        });
    });
    
    describe('CartUpdateItem Events', function () {
        it('可以创建CartUpdateItemBefore事件', function () {
            $itemId = 101;
            $quantity = 3;
            $event = new CartUpdateItemBefore($itemId, $quantity);
            
            expect($event)->toBeInstanceOf(CartUpdateItemBefore::class);
            
            if (property_exists($event, 'itemId')) {
                expect($event->itemId)->toBe($itemId);
            }
            if (property_exists($event, 'quantity')) {
                expect($event->quantity)->toBe($quantity);
            }
        });
        
        it('可以创建CartUpdateItemAfter事件', function () {
            $mockCartItem = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCartItem::class);
            $event = new CartUpdateItemAfter($mockCartItem);
            
            expect($event)->toBeInstanceOf(CartUpdateItemAfter::class);
            
            if (property_exists($event, 'item')) {
                expect($event->item)->toBe($mockCartItem);
            }
        });
    });
    
    it('事件可以被实例化', function () {
        // 创建模拟对象
        $mockCartable = Mockery::mock(\MobileNowGroup\ShoppingCart\Contracts\Cartable::class);
        $mockCartItem = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCartItem::class);
        $mockCart = Mockery::mock(\MobileNowGroup\ShoppingCart\Models\ShoppingCart::class);
        
        $events = [
            new CartAddProductBefore($mockCartable, 1, false),
            new CartAddProductAfter($mockCartable),
            new CartCollectTotalBefore($mockCart),
            new CartCollectTotalAfter($mockCart),
            new CartEmptyBefore($mockCart),
            new CartEmptyAfter($mockCart),
            new CartGetBefore(123),
            new CartGetAfter($mockCart),
            new CartRemoveItemBefore(456),
            new CartRemoveItemAfter(456, $mockCart),
            new CartToggleBuyBefore('buy', true, 789),
            new CartToggleBuyAfter('buy', true, 789),
            new CartUpdateItemBefore(101, 2),
            new CartUpdateItemAfter($mockCartItem)
        ];
        
        foreach ($events as $event) {
            expect($event)->toBeObject();
        }
    });
});