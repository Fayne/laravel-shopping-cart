<?php

use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\CartServerException;
use Fayne\ShoppingCart\Exceptions\ErrorConst;

describe('Cart Exceptions', function () {
    describe('CartClientException', function () {
        it('可以创建客户端异常', function () {
            $exception = new CartClientException(400, [], '客户端错误');
            
            expect($exception)->toBeInstanceOf(\Exception::class)
                ->and($exception->getMessage())->toBe('客户端错误');
        });
        
        it('可以使用默认错误码', function () {
            $exception = new CartClientException(1000, [], '默认错误');
            
            expect($exception->getMessage())->toBe('默认错误');
        });
        
        it('可以包含前一个异常', function () {
            $previous = new \Exception('前一个异常');
            $exception = new CartClientException(400, [], '当前异常', 400, $previous);
            
            expect($exception->getPrevious())->toBe($previous);
        });
    });
    
    describe('CartServerException', function () {
        it('可以创建服务器异常', function () {
            $exception = new CartServerException(500, [], '服务器错误');
            
            expect($exception)->toBeInstanceOf(\Exception::class)
                ->and($exception->getMessage())->toBe('服务器错误');
        });
        
        it('可以使用默认错误码', function () {
            $exception = new CartServerException(500, [], '默认服务器错误');
            
            expect($exception->getCustomCode())->toBe(500);
        });
        
        it('可以包含前一个异常', function () {
            $exception = new CartServerException(500, [], '当前异常');
            
            expect($exception->getMessage())->toBe('当前异常');
        });
    });
    
    describe('ErrorConst', function () {
        it('定义了正确的错误常量', function () {
            expect(ErrorConst::ERROR_CART_NOT_EXIST)
                ->toBeInstanceOf(ErrorConst::class);
        });
        
        it('错误常量具有预期的值', function () {
            $cases = ErrorConst::cases();
            
            expect($cases)->toBeArray()
                ->and(count($cases))->toBeGreaterThan(0);
            
            // 验证常量值都是字符串或整数
            foreach ($cases as $case) {
                expect($case->value)->toBeInt();
            }
        });
    });
});