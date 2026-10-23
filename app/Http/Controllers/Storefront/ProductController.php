<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_if(! $product->is_active, 404);

        $product->load(['category', 'images']);

        return view('storefront.product-detail', ['product' => $product]);
    }
}
