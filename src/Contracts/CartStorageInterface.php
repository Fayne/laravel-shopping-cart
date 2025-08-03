<?php

namespace MobileNowGroup\ShoppingCart\Contracts;

use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;

/**
 * 购物车存储层接口
 * 定义购物车数据存储的标准操作
 */
interface CartStorageInterface
{
    /**
     * 获取购物车
     *
     * @param string $customerId
     * @param bool $isCheckout
     * @return CartModel|null
     */
    public function getCart(string $customerId, bool $isCheckout = false): ?CartModel;

    /**
     * 创建购物车
     *
     * @param array $data
     * @return CartModel
     */
    public function createCart(array $data): CartModel;

    /**
     * 更新购物车
     *
     * @param CartModel $cart
     * @return bool
     */
    public function updateCart(CartModel $cart): bool;

    /**
     * 删除购物车
     *
     * @param string $customerId
     * @return bool
     */
    public function deleteCart(string $customerId): bool;

    /**
     * 添加购物车项目
     *
     * @param int $cartId
     * @param array $itemData
     * @return ShoppingCartItem
     */
    public function addCartItem(int $cartId, array $itemData): ShoppingCartItem;

    /**
     * 更新购物车项目
     *
     * @param int $itemId
     * @param array $data
     * @return bool
     */
    public function updateCartItem(int $itemId, array $data): bool;

    /**
     * 删除购物车项目
     *
     * @param int $itemId
     * @return bool
     */
    public function removeCartItem(int $itemId): bool;

    /**
     * 获取购物车项目
     *
     * @param int $itemId
     * @return ShoppingCartItem|null
     */
    public function getCartItem(int $itemId): ?ShoppingCartItem;

    /**
     * 清空购物车
     *
     * @param int $cartId
     * @return bool
     */
    public function emptyCart(int $cartId): bool;

    /**
     * 批量更新购物车项目选中状态
     *
     * @param int $cartId
     * @param bool $selected
     * @return bool
     */
    public function toggleAllItems(int $cartId, bool $selected): bool;

    /**
     * 获取购物车统计信息
     *
     * @param int $cartId
     * @return array
     */
    public function getCartStats(int $cartId): array;
}