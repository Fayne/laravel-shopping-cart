<?php

use Illuminate\Support\Facades\Route;
use MobileNowGroup\ShoppingCart\Controllers\CartController;

/*
|--------------------------------------------------------------------------
| Auth API Routes -- don't need middleware token
|--------------------------------------------------------------------------
*/

Route::prefix('modules/shopping-cart')
    ->middleware(['api', 'auth:sanctum'])
    ->name('modules.shopping-cart.')
    ->group(function () {
        Route::apiResource('cart', CartController::class)->except(['index']);
        Route::post('apply-coupon', [CartController::class, 'applyCoupon']);
        Route::delete('remove-coupon', [CartController::class, 'removeCoupon']);
    });
