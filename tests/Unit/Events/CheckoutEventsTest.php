<?php

use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\BackFromOnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\OnePageBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ShowBefore;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ToggleBuyAfter;
use MobileNowGroup\ShoppingCart\Events\ShopCart\Checkout\ToggleBuyBefore;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use Illuminate\Contracts\Auth\Authenticatable;


function createMockCartForCheckout($cartId = 1, $customerId = 123)
{
    $cart = Mockery::mock(ShoppingCart::class);
    $cart->shouldReceive('getAttribute')->with('id')->andReturn($cartId);
    $cart->shouldReceive('getAttribute')->with('customer_id')->andReturn($customerId);
    $cart->shouldReceive('setAttribute')->andReturnSelf();
    $cart->shouldIgnoreMissing();
    $cart->id = $cartId;
    $cart->customer_id = $customerId;
    return $cart;
}

function createMockUserForCheckout($userId = 123)
{
    return new class($userId) implements Authenticatable {
        private $id;
        
        public function __construct($id) {
            $this->id = $id;
        }
        
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return $this->id; }
        public function getAuthPassword() { return 'password'; }
        public function getAuthPasswordName() { return 'password'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
    };
}

describe('Checkout Events', function () {
    afterEach(function () {
        Mockery::close();
    });
    
    describe('BackFromOnePage Events', function () {
        it('可以创建BackFromOnePageBefore事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new BackFromOnePageBefore($cart, $user);
            
            expect($event)->toBeInstanceOf(BackFromOnePageBefore::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
        
        it('可以创建BackFromOnePageAfter事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new BackFromOnePageAfter($cart, $user);
            
            expect($event)->toBeInstanceOf(BackFromOnePageAfter::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
    });
    
    describe('OnePage Events', function () {
        it('可以创建OnePageBefore事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new OnePageBefore($cart, $user);
            
            expect($event)->toBeInstanceOf(OnePageBefore::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
        
        it('可以创建OnePageAfter事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new OnePageAfter($cart, $user);
            
            expect($event)->toBeInstanceOf(OnePageAfter::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
    });
    
    describe('Show Events', function () {
        it('可以创建ShowBefore事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new ShowBefore($cart, $user);
            
            expect($event)->toBeInstanceOf(ShowBefore::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
        
        it('可以创建ShowAfter事件', function () {
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new ShowAfter($cart, $user);
            
            expect($event)->toBeInstanceOf(ShowAfter::class);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
    });
    
    describe('ToggleBuy Events', function () {
        it('可以创建ToggleBuyBefore事件', function () {
            $type = 'item';
            $selected = true;
            $itemId = 123;
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new ToggleBuyBefore($type, $selected, $itemId, $cart, $user);
            
            expect($event)->toBeInstanceOf(ToggleBuyBefore::class);
            expect($event->type)->toBe($type);
            expect($event->selected)->toBe($selected);
            expect($event->itemId)->toBe($itemId);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
        
        it('可以创建ToggleBuyAfter事件', function () {
            $type = 'item';
            $selected = true;
            $itemId = 123;
            $cart = createMockCartForCheckout(1, 123);
            $user = createMockUserForCheckout(123);
            $event = new ToggleBuyAfter($type, $selected, $itemId, $cart, $user);
            
            expect($event)->toBeInstanceOf(ToggleBuyAfter::class);
            expect($event->type)->toBe($type);
            expect($event->selected)->toBe($selected);
            expect($event->itemId)->toBe($itemId);
            expect($event->cart)->toBe($cart);
            expect($event->user)->toBe($user);
        });
    });
    
    it('可以处理复杂的结账流程', function () {
        // 模拟完整的结账流程事件序列
        $cart = createMockCartForCheckout(1, 123);
        $user = createMockUserForCheckout(123);
        
        // 显示结账页面
        $showBefore = new ShowBefore($cart, $user);
        expect($showBefore)->toBeInstanceOf(ShowBefore::class);
        
        $showAfter = new ShowAfter($cart, $user);
        expect($showAfter)->toBeInstanceOf(ShowAfter::class);
        
        // 处理一页结账
        $onePageBefore = new OnePageBefore($cart, $user);
        expect($onePageBefore)->toBeInstanceOf(OnePageBefore::class);
        
        $onePageAfter = new OnePageAfter($cart, $user);
        expect($onePageAfter)->toBeInstanceOf(OnePageAfter::class);
    });
    
    it('所有Checkout事件都可以被序列化', function () {
        // 使用 null 参数测试序列化，避免 Mockery 对象序列化问题
        $events = [
            new BackFromOnePageBefore(),
            new BackFromOnePageAfter(),
            new OnePageBefore(),
            new OnePageAfter(),
            new ShowBefore(),
            new ShowAfter(),
            new ToggleBuyBefore('item', true, 123),
            new ToggleBuyAfter('item', true, 123)
        ];
        
        foreach ($events as $event) {
            $serialized = serialize($event);
            $unserialized = unserialize($serialized);
            
            expect($unserialized)->toBeInstanceOf(get_class($event));
        }
    });
    
    it('可以验证事件数据的完整性', function () {
        $cart = createMockCartForCheckout(1, 123);
        $user = createMockUserForCheckout(123);
        
        $event = new OnePageBefore($cart, $user);
        
        // 验证事件包含所有必要的数据
        expect($event)->toBeInstanceOf(OnePageBefore::class);
        expect($event->cart)->toBe($cart);
        expect($event->user)->toBe($user);
    });
});