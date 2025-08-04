<?php

namespace MobileNowGroup\ShoppingCart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 购物车请求基类
 */
class CartRequest extends FormRequest
{
    /**
     * 确定用户是否有权限进行此请求
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取应用于请求的验证规则
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * 获取验证错误的自定义属性
     */
    public function attributes(): array
    {
        return [
            'product_id' => '商品ID',
            'quantity' => '数量',
        ];
    }

    /**
     * 获取验证错误的自定义消息
     */
    public function messages(): array
    {
        return [
            'product_id.required' => '商品ID不能为空',
            'product_id.integer' => '商品ID必须是整数',
            'product_id.min' => '商品ID必须大于0',
            'quantity.required' => '数量不能为空',
            'quantity.integer' => '数量必须是整数',
            'quantity.min' => '数量必须大于0',
        ];
    }
}