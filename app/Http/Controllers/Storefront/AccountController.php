<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function orders(): View
    {
        $customer = Auth::guard('customer')->user();

        $orders = $customer->orders()
            ->with(['items.product'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('storefront.auth.orders', compact('orders'));
    }
}
