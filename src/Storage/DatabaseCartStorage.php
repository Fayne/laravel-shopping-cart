<?php

namespace MobileNowGroup\ShoppingCart\Storage;

use Illuminate\Support\Facades\DB;
use MobileNowGroup\ShoppingCart\Contracts\CartStorageInterface;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart as CartModel;
use MobileNowGroup\ShoppingCart\Models\ShoppingCartItem;
use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;
use MobileNowGroup\ShoppingCart\Exceptions\ErrorConst;

/**
 * 数据库存储层实现
 * 负责购物车数据的数据库操作
 */
class DatabaseCartStorage implements CartStorageInterface
{
    /**
     * 获取购物车
     */
    public function getCart(string $customerId, bool $isCheckout = false): ?CartModel
    {
        return CartModel::withWhereHas('items', function ($query) use ($isCheckout) {
            if ($isCheckout) {
                $query->where('selected', true);
            }
            $query->with('itemable');
        })->firstWhere([
            'customer_id' => $customerId,
            'is_active' => true,
        ]);
    }

    /**
     * 创建购物车
     */
    public function createCart(array $data): CartModel
    {
        try {
            return CartModel::create($data);
        } catch (\Exception $e) {
            throw new CartServerException(ErrorConst::ERROR_CREATE_CART_INSTANCE->value);
        }
    }

    /**
     * 更新购物车
     */
    public function updateCart(CartModel $cart): bool
    {
        try {
            return $cart->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除购物车
     */
    public function deleteCart(string $customerId): bool
    {
        try {
            return CartModel::where('customer_id', $customerId)
                ->where('is_active', true)
                ->delete() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 添加购物车项目
     */
    public function addCartItem(int $cartId, array $itemData): ShoppingCartItem
    {
        $itemData['cart_id'] = $cartId;
        return ShoppingCartItem::create($itemData);
    }

    /**
     * 添加购物车项目 (别名)
     */
    public function addItem(int $cartId, array $itemData): ShoppingCartItem
    {
        return $this->addCartItem($cartId, $itemData);
    }

    /**
     * 更新购物车项目
     */
    public function updateCartItem(int $itemId, array $data): bool
    {
        try {
            return ShoppingCartItem::where('id', $itemId)->update($data) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除购物车项目
     */
    public function removeCartItem(int $itemId): bool
    {
        try {
            return ShoppingCartItem::destroy($itemId) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取购物车项目
     */
    public function getCartItem(int $itemId): ?ShoppingCartItem
    {
        return ShoppingCartItem::find($itemId);
    }

    /**
     * 清空购物车
     */
    public function emptyCart(int $cartId): bool
    {
        try {
            return ShoppingCartItem::where('cart_id', $cartId)->delete() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 批量更新购物车项目选中状态
     */
    public function toggleAllItems(int $cartId, bool $selected): bool
    {
        try {
            return DB::transaction(function () use ($cartId, $selected) {
                return ShoppingCartItem::where('cart_id', $cartId)
                    ->update(['selected' => $selected]) >= 0;
            });
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取购物车统计信息
     */
    public function getCartStats(int $cartId): array
    {
        $items = ShoppingCartItem::where('cart_id', $cartId)->get();
        
        return [
            'items_count' => $items->count(),
            'items_qty' => $items->sum('quantity'),
            'selected_count' => $items->where('selected', true)->count(),
            'selected_qty' => $items->where('selected', true)->sum('quantity'),
        ];
    }

    /**
     * 批量更新购物车项目
     */
    public function updateItems(int $cartId, array $items): bool
    {
        try {
            return DB::transaction(function () use ($cartId, $items) {
                foreach ($items as $itemData) {
                    if (isset($itemData['id'])) {
                        $updateData = $itemData;
                        unset($updateData['id']);
                        ShoppingCartItem::where('id', $itemData['id'])
                            ->where('cart_id', $cartId)
                            ->update($updateData);
                    }
                }
                return true;
            });
        } catch (\Exception $e) {
            return false;
        }
    }
}