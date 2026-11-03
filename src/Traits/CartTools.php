<?php

namespace Fayne\ShoppingCart\Traits;

use Illuminate\Support\Facades\Cache;
use Fayne\ShoppingCart\Exceptions\CartClientException;
use Fayne\ShoppingCart\Exceptions\CartServerException;
use Fayne\ShoppingCart\Models\ShoppingCart;
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
        $this->transaction(function () {
            if (Cache::has($this->getCacheKeyForDeactivatedCartId())) {
                /** @var string $deactivatedCartId */
                $deactivatedCartId = Cache::get($this->getCacheKeyForDeactivatedCartId());

                $deactivatedCart = ShoppingCart::where('customer_id', auth()->id())->where('is_temp', false)->where('is_active', false)->find($deactivatedCartId);
                if (!$deactivatedCart) {
                    Cache::forget($this->getCacheKeyForDeactivatedCartId());

                    return;
                }

                if ($this->getCart()) {

                    foreach ($deactivatedCart->items as $deactivatedCartItem) {
                        $this->addProduct($deactivatedCartItem->itemable, $deactivatedCartItem->quantity);
                    }

                    $this->collectTotals();
                    $deactivatedCart->delete();
                } else {
                    $deactivatedCart->is_active = true;
                    $this->storage->updateCart($deactivatedCart);
                }
                Cache::forget($this->getCacheKeyForDeactivatedCartId());
            }

        });
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
        $this->transaction(function () {
            if (request()->get('is_buy_now')) {
                if ($deactivatedCart = $this->getCart()) {
                    if (!$deactivatedCart->is_temp) {
                        Cache::put($this->getCacheKeyForDeactivatedCartId(), $deactivatedCart->id);
                    }

                    $this->deActivateCart();
                }
            }

        });
    }

    /**
     * This method will reactivate the cart which is deactivated at the time of buy now functionality.
     *
     * @return void
     */
    public function activateCartIfCacheHasDeactivatedCartId(): void
    {
        $this->transaction(function () {
            if ($deactivatedCartId = $this->getDeactivateCartId()) {

                $this->activateCart($deactivatedCartId);

                Cache::forget($this->getCacheKeyForDeactivatedCartId());
            }

        });
    }

    /**
     * @return mixed
     */
    public function getDeactivateCartId(): mixed
    {
        return Cache::rememberForever($this->getCacheKeyForDeactivatedCartId(), function () {
            return ShoppingCart::firstWhere([
                'customer_id' => auth()->id(),
                'is_active' => false,
                'is_temp' => false,
            ])->id ?? null;
        });
    }

    /**
     * Deactivates current cart.
     *
     * @return void
     */
    public function deActivateCart(): void
    {
        $this->transaction(function () {
            if ($cart = $this->getCart()) {
                $cart->is_active = false;
                $this->storage->updateCart($cart);

            }

        });
    }

    /**
     * Activate the cart by id.
     *
     * @param  int  $cartId
     * @return void
     */
    public function activateCart($cartId): void
    {
        $this->transaction(function () use ($cartId) {
            $cart = ShoppingCart::where([
                'id' => $cartId,
                'customer_id' => auth()->id(),
                'is_temp' => false,
            ])->first();
            if ($cart) {
                ShoppingCart::where('customer_id', auth()->id())->where('is_active', true)->update(['is_active' => false]);
                $cart->is_active = true;
                $this->storage->updateCart($cart);
            }

        });
    }

    /**
     * @return string
     */
    public function getCacheKeyForDeactivatedCartId()
    {
        return sprintf('user_%s_deactivated_cart_id', auth()->id());
    }
}
