<?php

use Illuminate\Support\Facades\Route;
use MobileNowGroup\ShoppingCart\Http\Controllers\CartController;

/*
|--------------------------------------------------------------------------
| Auth API Routes -- don't need middleware token
|--------------------------------------------------------------------------
*/

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
    });
