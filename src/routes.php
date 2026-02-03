<?php

use Illuminate\Support\Facades\Route;
use MobileNowGroup\ShoppingCart\Http\Controllers\CartController;
use MobileNowGroup\ShoppingCart\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| Auth API Routes -- need token
|--------------------------------------------------------------------------
*/
Route::prefix('modules/shopping-cart')
    ->middleware(['api', 'auth:sanctum'])
    ->name('modules.shopping-cart.')
    ->group(function () {
        Route::apiSingleton('', CartController::class)->creatable();
        Route::post('apply-coupon', [CartController::class, 'applyCoupon']);
        Route::delete('remove-coupon', [CartController::class, 'removeCoupon']);
        Route::delete('remove-item', [CartController::class, 'removeItem']);
        Route::prefix('checkout')
            ->group(function () {
                Route::post('cart-item-selection', [CheckoutController::class, 'setCartItemSelection']);
                Route::get('', [CheckoutController::class, 'show']);
                Route::get('one-page', [CheckoutController::class, 'onePage']);
                Route::get('back-from-one-page', [CheckoutController::class, 'backFromOnePage']);
            });
    });
