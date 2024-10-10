<?php

namespace MobileNowGroup\ShoppingCart\Exceptions;

enum ErrorConst: int
{
    case CODE_ERROR = -1;

    case SUCCESS_CODE = 0;

    case ERROR_CART_NOT_CREATED = 1000;

    case ERROR_ADD_INACTIVE_PRODUCT = 1002;

    case ERROR_PRODUCT_NOT_AVAILABLE = 1003;

    case ERROR_QUANTITY_NOT_AVAILABLE = 1004;

    case ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE = 1005;

    case ERROR_CREATE_CART_INSTANCE = 1006;
    case ERROR_PRODUCT_NOT_EXIST = 1007;
    case ERROR_INVALID_COUPON_CODE = 1008;
    case ERROR_CANNOT_APPLY_COUPON = 1009;
    case ERROR_NOT_INSTANCE_CARTABLE = 1010;
    case ERROR_CART_NOT_EXIST = 1011;
    case ERROR_COUPON_INVALID = 1012;
    case ERROR_REMOVE_COUPON = 1013;

    /**
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array
     */
    public static function labels(): array
    {
        return array_map(fn ($case) => $case->name, self::cases());
    }

    /**
     * @param int $errorCode
     * @return string
     */
    public static function errorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            self::CODE_ERROR->value => 'error',
            self::SUCCESS_CODE->value => 'success',
            self::ERROR_CART_NOT_CREATED->value => '商品无法添加到购物车，请稍后再试。',
            self::ERROR_ADD_INACTIVE_PRODUCT->value => '未发布的商品不能添加到购物车中。',
            self::ERROR_PRODUCT_NOT_AVAILABLE->value => '商品处于未发布状态并已从购物车中删除。',
            self::ERROR_QUANTITY_NOT_AVAILABLE->value => '数量不能小于1。',
            self::ERROR_REQUESTED_QUANTITY_NOT_AVAILABLE->value => '所要求的数量不足，请稍后再试。',
            self::ERROR_CREATE_CART_INSTANCE->value => '在创建购物车时遇到了一些问题。',
            self::ERROR_PRODUCT_NOT_EXIST->value => '商品不存在。',
            self::ERROR_INVALID_COUPON_CODE->value => '优惠券代码无效。',
            self::ERROR_CANNOT_APPLY_COUPON->value => '优惠券无法使用。',
            self::ERROR_NOT_INSTANCE_CARTABLE->value => '必须是Cartable的一个实例',
            self::ERROR_CART_NOT_EXIST->value => '购物车不存在',
            self::ERROR_COUPON_INVALID->value => '优惠券code无效',
            self::ERROR_REMOVE_COUPON->value => '无法删除优惠券',
            default => 'error'
        };
    }
}
