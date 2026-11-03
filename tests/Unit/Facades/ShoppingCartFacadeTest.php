<?php

use Fayne\ShoppingCart\Facades\ShoppingCart;
use Fayne\ShoppingCart\ShoppingCart as ShoppingCartService;
use Illuminate\Support\Facades\Facade;

describe('ShoppingCart Facade', function () {
    it('可以解析到正确的服务类', function () {
        $facade = new ShoppingCart();
        expect($facade)->toBeInstanceOf(Facade::class);
        
        // 测试 Facade 的根访问器
        expect(ShoppingCart::getFacadeRoot())->toBeInstanceOf(ShoppingCartService::class);
    });
    
    it('可以通过Facade调用服务方法', function () {
        // Mock 用户认证
        $user = createMockUser(1);
        $this->actingAs($user);
        
        // 确保认证系统正确设置
        expect(auth()->check())->toBeTrue()
            ->and(auth()->id())->toBe(1);
        
        // 由于没有实际的存储实现，getCart 可能返回 null
         // 这里我们只测试方法可以被调用而不抛出异常
         $cart = ShoppingCart::getCart();
         expect($cart)->toBeNull(); // 在测试环境中，没有实际数据时返回 null 是正常的
     });
    
    it('Facade访问器返回正确的服务名称', function () {
        $reflection = new ReflectionClass(ShoppingCart::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        
        $accessor = $method->invoke(new ShoppingCart());
        expect($accessor)->toBe('shopping-cart');
    });
});

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
        public function id() { return $this->id; }
    };
}