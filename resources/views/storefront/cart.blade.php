@extends('storefront.layouts.app')

@section('title', 'Giỏ Hàng | CozaStore')
@section('headerClass', 'header-v4')

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">Giỏ Hàng</span>
    </div>
</div>

<div class="container p-t-40 p-b-85">
    <div class="wrap-table-shopping-cart">
        <table class="table-shopping-cart">
            <thead>
                <tr class="table_head">
                    <th class="column-1">Hình</th>
                    <th class="column-1">Sản Phẩm</th>
                    <th class="column-1">Giá</th>
                    <th class="column-1">Màu Sắc</th>
                    <th class="column-1">Dung Lượng</th>
                    <th class="column-1">Số Lượng</th>
                    <th class="column-1">Tổng</th>
                    <th class="column-1"></th>
                </tr>
            </thead>
            <tbody id="cart-items-body"></tbody>
        </table>
    </div>

    <div id="cart-empty" class="txt-center p-t-30" style="display:none;">
        <p class="p-b-20">Giỏ hàng của bạn đang trống.</p>
        <a href="{{ route('storefront.home') }}"
            class="flex-c-m stext-101 cl0 size-103 bg3 bor14 hov-btn3 p-lr-15 trans-04"
            style="display:inline-flex; margin: 0 auto;">Tiếp Tục Mua Sắm</a>
    </div>

    <div class="flex-w flex-sb-m bor15 p-t-18 p-b-15 p-lr-40 p-lr-15-sm">
        <div class="flex-w flex-m m-r-20 m-tb-5">
            <span class="mtext-110 cl2" style="font-weight: bold;">Tổng: <span id="cart-total">0</span> ₫</span>
        </div>
        <div class="flex-w flex-m m-r-20 m-tb-5">
            <a id="cart-clear-btn" href="#"
                class="flex-c-m stext-101 cl6 size-103 bor8 hov-btn1 p-lr-15 trans-04 m-r-10">Xóa Tất Cả</a>
            <a href="{{ route('storefront.checkout') }}"
                class="flex-c-m stext-101 cl0 size-103 bg3 bor14 hov-btn3 p-lr-15 trans-04">Thanh Toán</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('storefront/js/pages/cart-page.js') }}"></script>
@endpush
