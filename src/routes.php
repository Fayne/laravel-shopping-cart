<?php

use Illuminate\Support\Facades\Route;
use MobileNowGroup\ShoppingCart\Http\Controllers\CartController;
use MobileNowGroup\ShoppingCart\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| Auth API Routes -- need token
|--------------------------------------------------------------------------
*/
Route::prefix(config('shopping-cart.routes.prefix'))
    ->middleware(['api', 'auth:sanctum'])
    ->name(config('shopping-cart.routes.name'))
    ->group(function () {
        Route::apiSingleton('', CartController::class)->creatable();
        Route::post('apply-coupon', [CartController::class, 'applyCoupon'])->name('apply-coupon');
        Route::delete('remove-coupon', [CartController::class, 'removeCoupon'])->name('remove-coupon');
        Route::delete('remove-item', [CartController::class, 'removeItem'])->name('remove-item');
        Route::prefix('checkout')
            ->group(function () {
                Route::post('cart-item-selection', [CheckoutController::class, 'setCartItemSelection']);
                Route::get('', [CheckoutController::class, 'show']);
                Route::get('one-page', [CheckoutController::class, 'onePage']);
                Route::get('back-from-one-page', [CheckoutController::class, 'backFromOnePage']);
            });
    });
