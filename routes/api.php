<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('categories', CategoryController::class);

    Route::get('media', [MediaController::class, 'index']);
    Route::post('media/upload', [MediaController::class, 'upload']);

    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/images', [ProductImageController::class, 'store']);
    Route::patch('products/{product}/images/{image}', [ProductImageController::class, 'update']);
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy']);

    Route::apiResource('warehouses', WarehouseController::class);

    Route::get('stock', [StockController::class, 'index']);
    Route::get('stock/movements', [StockController::class, 'movements']);
    Route::post('stock/in', [StockController::class, 'stockIn']);
    Route::post('stock/adjust', [StockController::class, 'adjust']);
    Route::post('stock/transfer', [StockController::class, 'transfer']);
    Route::get('product-units', [ProductUnitController::class, 'index']);

    Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order}/payments', [OrderController::class, 'pay']);
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice']);

    Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus']);

    Route::get('roles', [RoleController::class, 'index']);
});
