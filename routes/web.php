<?php

use App\Http\Controllers\Api\Storefront\OrderController as StorefrontOrderWebController;
use App\Http\Controllers\Storefront\AccountController as StorefrontAccountController;
use App\Http\Controllers\Storefront\AuthController as StorefrontAuthController;
use App\Http\Controllers\Storefront\HomeController as StorefrontHomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::name('storefront.')->group(function () {
    Route::get('/', [StorefrontHomeController::class, 'index'])->name('home');
    Route::get('/san-pham/{product:slug}', [StorefrontProductController::class, 'show'])->name('product');
    // hideCartDrawer: dang o chinh trang gio hang roi nen khong can hien
    // them drawer gio hang tren header nua (trung lap, de nham lan).
    Route::get('/gio-hang', fn () => view('storefront.cart', ['hideCartDrawer' => true]))->name('cart');
    Route::get('/thanh-toan', fn () => view('storefront.checkout'))->name('checkout');

    // Dung CHUNG controller voi route API '/api/storefront/orders', nhung
    // goi qua web.php de CO session - nho vay neu khach dang dang nhap,
    // don hang se duoc gan dung customer_id ngay, khong phu thuoc viec SDT
    // nhap tren form co khop tuyet doi hay khong.
    Route::post('/thanh-toan', [StorefrontOrderWebController::class, 'store'])->name('checkout.submit');

    // Xem truoc giam gia khi ap ma khuyen mai (khong tao don) - throttle de
    // tranh bi do ma khuyen mai hang loat.
    Route::post('/thanh-toan/khuyen-mai', [StorefrontOrderWebController::class, 'previewPromotion'])
        ->name('checkout.promotion.preview')
        ->middleware('throttle:20,1');

    Route::middleware('guest:customer')->group(function () {
        Route::get('/dang-nhap', [StorefrontAuthController::class, 'showLogin'])->name('login');
        Route::post('/dang-nhap', [StorefrontAuthController::class, 'login'])->name('login.submit');
        Route::get('/dang-ky', [StorefrontAuthController::class, 'showRegister'])->name('register');
        Route::post('/dang-ky', [StorefrontAuthController::class, 'register'])->name('register.submit');
    });
    Route::post('/dang-xuat', [StorefrontAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/tai-khoan/don-hang', [StorefrontAccountController::class, 'orders'])->name('account.orders');
    });
});

Route::view('/login', 'spa.app')->name('login');

Route::fallback(function (Request $request) {
    if ($request->is('api/*') || $request->is('sanctum/*') || $request->is('.well-known/*')) {
        abort(404);
    }

    return response()->view('spa.app');
});
