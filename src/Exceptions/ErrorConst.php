<?php

namespace MobileNowGroup\ShoppingCart\Exceptions;

class ErrorConst
{
    const CODE_ERROR = -1;
    const SUCCESS_CODE = 0;
    const ERROR_CART_NOT_CREATED = 10000;
    const ERROR_ADD_INACTIVE_PRODUCT = 10002;
    const ERROR_PRODUCT_NOT_AVAILABLE = 10003;
    const ERROR_QUANTITY_NOT_AVAILABLE = 10004;
    const ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE = 10005;
    const ERROR_CREATE_CART_INSTANCE = 10006;
    const ERROR_PRODUCT_NOT_EXIST = 10007;
    const ERROR_INVALID_COUPON_CODE = 10008;
    const ERROR_CANNOT_APPLY_COUPON = 10009;
    const ERROR_NOT_INSTANCE_CARTABLE = 10010;
    const ERROR_CART_NOT_EXIST = 10011;
    const ERROR_MESSAGE = [
        self::CODE_ERROR => 'error',
        self::ERROR_CART_NOT_CREATED => '商品无法添加到购物车，请稍后再试。',
        self::ERROR_ADD_INACTIVE_PRODUCT => '未发布的商品不能添加到购物车中。',
        self::ERROR_PRODUCT_NOT_AVAILABLE => '商品处于未发布状态并已从购物车中删除。',
        self::ERROR_QUANTITY_NOT_AVAILABLE => '数量不能小于1。',
        self::ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE => '所要求的数量不足，请稍后再试。',
        self::ERROR_CREATE_CART_INSTANCE => '在创建购物车时遇到了一些问题。',
        self::ERROR_PRODUCT_NOT_EXIST => '商品不存在。',
        self::ERROR_INVALID_COUPON_CODE => '优惠券代码无效。',
        self::ERROR_CANNOT_APPLY_COUPON => '优惠券无法使用。',
        self::ERROR_NOT_INSTANCE_CARTABLE => '必须是Cartable的一个实例',
        self::ERROR_CART_NOT_EXIST => '购物车不存在',
    ];
}
