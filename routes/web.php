<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'spa.app');
Route::view('/login', 'spa.app')->name('login');

Route::fallback(function (Request $request) {
    if ($request->is('api/*') || $request->is('sanctum/*') || $request->is('.well-known/*')) {
        abort(404);
    }

    return response()->view('spa.app');
});
