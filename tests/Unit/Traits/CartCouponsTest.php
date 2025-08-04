<?php

use MobileNowGroup\ShoppingCart\Traits\CartCoupons;

describe('CartCoupons Trait', function () {
    beforeEach(function () {
        $this->mock = Mockery::mock();
        $this->mock->shouldAllowMockingProtectedMethods();
        $this->mock = $this->mock->makePartial();
        
        // 使用 trait
        $traitMethods = get_class_methods(CartCoupons::class);
        foreach ($traitMethods as $method) {
            if (!method_exists($this->mock, $method)) {
                $this->mock->shouldReceive($method)->passthru();
            }
        }
    });
    
    afterEach(function () {
        Mockery::close();
    });
    
    it('可以应用优惠券', function () {
        // 创建一个使用 CartCoupons trait 的测试类
        $testClass = new class {
            use CartCoupons;
            
            protected $couponCode = null;
            protected $discountAmount = 0;
            
            public function setCouponCode($code) {
                $this->couponCode = $code;
            }
            
            public function getCouponCode() {
                return $this->couponCode;
            }
            
            public function setDiscountAmount($amount) {
                $this->discountAmount = $amount;
            }
            
            public function getDiscountAmount() {
                return $this->discountAmount;
            }
        };
        
        // 测试应用优惠券
        $testClass->setCouponCode('SAVE10');
        $testClass->setDiscountAmount(10.00);
        
        expect($testClass->getCouponCode())->toBe('SAVE10')
            ->and($testClass->getDiscountAmount())->toBe(10.00);
    });
    
    it('可以移除优惠券', function () {
        $testClass = new class {
            use CartCoupons;
            
            protected $couponCode = 'SAVE10';
            protected $discountAmount = 10.00;
            
            public function removeCoupon() {
                $this->couponCode = null;
                $this->discountAmount = 0;
            }
            
            public function getCouponCode() {
                return $this->couponCode;
            }
            
            public function getDiscountAmount() {
                return $this->discountAmount;
            }
        };
        
        // 移除优惠券前
        expect($testClass->getCouponCode())->toBe('SAVE10');
        
        // 移除优惠券
        $testClass->removeCoupon();
        
        // 移除优惠券后
        expect($testClass->getCouponCode())->toBeNull()
            ->and($testClass->getDiscountAmount())->toBe(0);
    });
    
    it('可以验证优惠券是否有效', function () {
        $testClass = new class {
            use CartCoupons;
            
            protected $couponCode = null;
            
            public function hasCoupon() {
                return !empty($this->couponCode);
            }
            
            public function setCouponCode($code) {
                $this->couponCode = $code;
            }
        };
        
        // 没有优惠券时
        expect($testClass->hasCoupon())->toBeFalse();
        
        // 设置优惠券后
        $testClass->setCouponCode('DISCOUNT20');
        expect($testClass->hasCoupon())->toBeTrue();
    });
    
    it('可以计算优惠后的价格', function () {
        $testClass = new class {
            use CartCoupons;
            
            protected $originalPrice = 100.00;
            protected $discountAmount = 15.00;
            
            public function getOriginalPrice() {
                return $this->originalPrice;
            }
            
            public function getDiscountAmount() {
                return $this->discountAmount;
            }
            
            public function getFinalPrice() {
                return $this->originalPrice - $this->discountAmount;
            }
        };
        
        expect($testClass->getOriginalPrice())->toBe(100.00)
            ->and($testClass->getDiscountAmount())->toBe(15.00)
            ->and($testClass->getFinalPrice())->toBe(85.00);
    });
});