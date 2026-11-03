<?php

use Fayne\ShoppingCart\Http\Controllers\CartController;
use Fayne\ShoppingCart\ShoppingCart;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Fayne\ShoppingCart\Http\Requests\Cart\StoreRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\UpdateRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\RemoveRequest;
use Fayne\ShoppingCart\Http\Requests\Cart\EmptyCartRequest;
use Fayne\ShoppingCart\Facades\ShoppingCart as ShoppingCartFacade;
use Fayne\ShoppingCart\Models\ShoppingCart as ShoppingCartModel;
use Illuminate\Contracts\Auth\Authenticatable;

describe('CartController', function () {
    beforeEach(function () {
        $this->controller = new CartController;
        $this->mockShoppingCart = Mockery::mock(ShoppingCart::class);
        $this->mockRequest = Mockery::mock(Request::class);
        $this->mockStoreRequest = Mockery::mock(StoreRequest::class);
        $this->mockUpdateRequest = Mockery::mock(UpdateRequest::class);
        $this->mockRemoveRequest = Mockery::mock(RemoveRequest::class);
        $this->mockEmptyRequest = Mockery::mock(EmptyCartRequest::class);

        // Mock 用户认证
        $this->actingAs(createMockUserForController(1));
    });

    afterEach(function () {
        Mockery::close();
    });

    it('可以显示购物车', function () {
        $mockCart = Mockery::mock(ShoppingCartModel::class);
        $mockCart->shouldReceive('getAttribute')->andReturn(null);
        $mockCart->shouldReceive('toArray')->andReturn([]);

        ShoppingCartFacade::shouldReceive('collectTotals')
            ->once();

        ShoppingCartFacade::shouldReceive('getCart')
            ->once()
            ->andReturn($mockCart);

        $response = $this->controller->show();
        expect($response)->toBeInstanceOf(JsonResponse::class);
    });

    it('可以更新购物车商品', function () {
        $this->mockUpdateRequest->shouldReceive('validated')->once()->andReturn(['item_id' => 'item', 'quantity' => 2]);
        ShoppingCartFacade::shouldReceive('updateItems')->with('item', 2)->once()->andReturn(null);
        $response = $this->controller->update($this->mockUpdateRequest);
        expect($response)->toBeInstanceOf(JsonResponse::class);
    });

    it('可以删除购物车商品', function () {
        $mockCart = Mockery::mock(ShoppingCartModel::class);
        $mockCart->shouldReceive('getAttribute')->andReturn(null);
        $mockCart->shouldReceive('toArray')->andReturn([]);

        $this->mockRemoveRequest->shouldReceive('validated')
            ->with('item_id')
            ->andReturn(1);

        ShoppingCartFacade::shouldReceive('removeItem')
            ->with(1)
            ->once();

        ShoppingCartFacade::shouldReceive('getCart')
            ->once()
            ->andReturn($mockCart);

        $response = $this->controller->removeItem($this->mockRemoveRequest);
        expect($response)->toBeInstanceOf(JsonResponse::class);
    });

    it('可以清空购物车', function () {
        $mockCart = Mockery::mock(ShoppingCartModel::class);
        $mockCart->shouldReceive('getAttribute')->andReturn(null);
        $mockCart->shouldReceive('toArray')->andReturn([]);

        ShoppingCartFacade::shouldReceive('emptyCart')
            ->once()
            ->andReturn($mockCart);

        $response = $this->controller->destroy($this->mockEmptyRequest);
        expect($response)->toBeInstanceOf(JsonResponse::class);
    });
});

// 辅助函数
function createMockUserForController($id = 1)
{
    return new class($id) implements Authenticatable {
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
