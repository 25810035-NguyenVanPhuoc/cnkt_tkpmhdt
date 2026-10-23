<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Mỗi category hiển thị 3 tab (Sản Phẩm Mới / Giá Tốt Nhất / Bán Chạy) giống
     * theme gốc — vì không có dữ liệu lượt bán/khuyến mãi riêng, "Bán Chạy" dùng
     * thứ tự mặc định (id) làm đại diện, 2 tab còn lại sắp theo ngày tạo / giá.
     */
    public function index(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        foreach ($categories as $category) {
            $newest = Product::where('category_id', $category->id)
                ->where('is_active', true)->with('images')->latest('id')->limit(8)->get();

            $cheapest = Product::where('category_id', $category->id)
                ->where('is_active', true)->with('images')->orderBy('sale_price')->limit(8)->get();

            $bestseller = Product::where('category_id', $category->id)
                ->where('is_active', true)->with('images')->orderBy('id')->limit(8)->get();

            $category->setRelation('newestProducts', $newest);
            $category->setRelation('cheapestProducts', $cheapest);
            $category->setRelation('bestsellerProducts', $bestseller);
        }

        return view('storefront.home', ['categories' => $categories]);
    }
}
