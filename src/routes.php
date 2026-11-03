<?php

use Illuminate\Support\Facades\Route;
use Fayne\ShoppingCart\Http\Controllers\CartController;
use Fayne\ShoppingCart\Http\Controllers\CheckoutController;
use Fayne\ShoppingCart\Http\Controllers\InstantPurchaseController;
use Fayne\ShoppingCart\Http\Controllers\PendingCheckoutsController;

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
        Route::patch('item-selection', [CartController::class, 'updateItemSelection'])->name('update-item-selection');
        Route::post('checkout', CheckoutController::class)->middleware('auth')->name('checkout');
        Route::get('pending-checkouts', [PendingCheckoutsController::class, 'index'])->middleware(['auth'])->name('pending-checkouts.index');
        Route::post('instant-purchase', [InstantPurchaseController::class, 'store'])->name('instant-purchase.store');
        Route::delete('instant-purchase', [InstantPurchaseController::class, 'destroy'])->name('instant-purchase.destroy');
    });
