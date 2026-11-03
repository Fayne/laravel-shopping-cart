<?php

namespace Fayne\ShoppingCart\Storage;

use Fayne\ShoppingCart\Contracts\CartStorageInterface;
use Fayne\ShoppingCart\Models\ShoppingCartItem;

/** Redis uses Laravel's serializer and the same invalidation rules as the cache driver. */
class RedisCartStorage extends CacheCartStorage
{
    public function __construct(CartStorageInterface $fallbackStorage, string $keyPrefix = 'cart', int $ttl = 86400, string $connection = 'default')
    {
        $store = 'shopping_cart_' . hash('sha256', $connection . '|' . $keyPrefix);
        config(["cache.stores.$store" => ['driver' => 'redis', 'connection' => $connection]]);
        parent::__construct($fallbackStorage, $keyPrefix . ':v2', $ttl, $store);
    }

    public function addItem(int|string $cartId, array $itemData): ShoppingCartItem
    {
        return $this->addCartItem($cartId, $itemData);
    }

    public function removeItem(int|string $cartId, int|string $itemId): bool
    {
        $item = $this->getCartItem($itemId);

        return $item && (string) $item->cart_id === (string) $cartId && $this->removeCartItem($itemId);
    }

    public function getCartItems(int|string $cartId): array
    {
        return ShoppingCartItem::where('cart_id', $cartId)->get()->all();
    }
}
