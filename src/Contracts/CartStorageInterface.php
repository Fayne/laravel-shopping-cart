<?php

namespace Fayne\ShoppingCart\Contracts;

use Fayne\ShoppingCart\Models\ShoppingCart as CartModel;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

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
     * @param int|string $cartId
     * @param array $itemData
     * @return ShoppingCartItem
     */
    public function addCartItem(int|string $cartId, array $itemData): ShoppingCartItem;

    /**
     * 更新购物车项目
     *
     * @param int|string $itemId
     * @param array $data
     * @return bool
     */
    public function updateCartItem(int|string $itemId, array $data): bool;

    /**
     * 删除购物车项目
     *
     * @param int|string $itemId
     * @return bool
     */
    public function removeCartItem(int|string $itemId): bool;

    /**
     * 获取购物车项目
     *
     * @param int|string $itemId
     * @return ShoppingCartItem|null
     */
    public function getCartItem(int|string $itemId): ?ShoppingCartItem;

    /**
     * 清空购物车
     *
     * @param int|string $cartId
     * @return bool
     */
    public function emptyCart(int|string $cartId): bool;

    /**
     * 批量更新购物车项目选中状态
     *
     * @param int|string $cartId
     * @param bool $selected
     * @return bool
     */
    public function toggleAllItems(int|string $cartId, bool $selected): bool;

    /**
     * 获取购物车统计信息
     *
     * @param int|string $cartId
     * @return array
     */
    public function getCartStats(int|string $cartId): array;

    /**
     * 批量更新购物车项目
     *
     * @param int|string $cartId
     * @param array $items
     * @return bool
     */
    public function updateItems(int|string $cartId, array $items): bool;

    /**
     * 获取用户购物车商品数量
     *
     * @param int|string $customerId
     * @return int
     */
    public function getItemsCount(int|string $customerId): int;

    /**
     * 获取用户购物车商品总数量
     *
     * @param string $customerId
     * @return int
     */
    public function getItemsQty(string $customerId): int;
}