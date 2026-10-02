<?php

use App\Http\Controllers\Storefront\HomeController as StorefrontHomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::name('storefront.')->group(function () {
    Route::get('/', [StorefrontHomeController::class, 'index'])->name('home');
    Route::get('/san-pham/{product:slug}', [StorefrontProductController::class, 'show'])->name('product');
    Route::get('/gio-hang', fn () => view('storefront.cart'))->name('cart');
    Route::get('/thanh-toan', fn () => view('storefront.checkout'))->name('checkout');
});

Route::view('/login', 'spa.app')->name('login');

Route::fallback(function (Request $request) {
    if ($request->is('api/*') || $request->is('sanctum/*') || $request->is('.well-known/*')) {
        abort(404);
    }

    return response()->view('spa.app');
});
