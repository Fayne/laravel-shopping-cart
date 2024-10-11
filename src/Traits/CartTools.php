<?php

namespace MobileNowGroup\ShoppingCart\Traits;

use Illuminate\Support\Facades\Cache;
use MobileNowGroup\ShoppingCart\Exceptions\CartClientException;
use MobileNowGroup\ShoppingCart\Exceptions\CartServerException;
use MobileNowGroup\ShoppingCart\Models\Enums\IsActive;
use MobileNowGroup\ShoppingCart\Models\Enums\IsTemp;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Cart tools. In this trait, you will get all sorted collections of
 * methods which can be used to manipulate cart and its items.
 *
 * Note: This trait will only work with the Cart facade. Unless and until,
 * you have all the required repositories in the parent class.
 */
trait CartTools
{
    /**
     * This method will merge deactivated cart, when a user suddenly navigates away
     * from the checkout after click the buy now button.
     *
     * @return void
     *
     * @throws CartClientException|CartServerException
     */
    public function mergeDeactivatedCart(): void
    {
        if (Cache::has($this->getCacheKeyForDeactivatedCartId())) {
            $deactivatedCartId = Cache::get($this->getCacheKeyForDeactivatedCartId());

            $deactivatedCart = ShoppingCart::find($deactivatedCartId);

            if ($this->getCart()) {

                foreach ($deactivatedCart->items as $deactivatedCartItem) {
                    $this->addProduct($deactivatedCartItem->product_id, $deactivatedCartItem->quantity);
                }

                $this->collectTotals();
            } else {
                $deactivatedCart->is_active = IsActive::IS_ACTIVE->value;
                $deactivatedCart->save();
            }
            Cache::forget($this->getCacheKeyForDeactivatedCartId());
        }
    }

    /**
     * This method will deactivate the current cart if
     * buy now is active.
     *
     * @return void
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deactivateCurrentCartIfBuyNowIsActive(): void
    {
        if (request()->get('is_buy_now')) {
            if ($deactivatedCart = $this->getCart()) {
                Cache::put($this->getCacheKeyForDeactivatedCartId(), $deactivatedCart->id);

                $this->deActivateCart($deactivatedCart);
            }
        }
    }

    /**
     * This method will reactivate the cart which is deactivated at the time of buy now functionality.
     *
     * @return void
     */
    public function activateCartIfCacheHasDeactivatedCartId(): void
    {
        if ($deactivatedCart = $this->getDeactivateCart()) {

            $this->activateCart($deactivatedCart);

            Cache::forget($this->getCacheKeyForDeactivatedCartId());
        }
    }

    /**
     * Get the deactivated cart.
     * It will return the cart object.
     * It is for common cart scenario and is not for is-buy-now.
     *
     * @return mixed
     */
    public function getDeactivateCart(): mixed
    {
        return Cache::rememberForever($this->getCacheKeyForDeactivatedCartId(), function () {
            return ShoppingCart::firstWhere([
                'user_id' => auth()->id(),
                'is_active' => IsActive::IS_DEACTIVATE->value,
                'is_temp' => IsTemp::IS_COMMON->value,
            ]) ?? null;
        });
    }

    /**
     * Deactivates current cart.
     *
     * @param ShoppingCart $cart
     * @return void
     */
    public function deActivateCart(ShoppingCart $cart): void
    {
        $cart->is_active = IsActive::IS_DEACTIVATE->value;
        $cart->save();
    }

    /**
     * Activate the cart
     *
     * @param  ShoppingCart  $cart
     * @return void
     */
    public function activateCart(ShoppingCart $cart): void
    {
        $cart->is_active = IsActive::IS_ACTIVE->value;
        $cart->save();
    }

    /**
     * @return string
     */
    public function getCacheKeyForDeactivatedCartId(): string
    {
        return sprintf('user_%s_deactivated_cart_id', auth()->id());
    }
}
