@extends('storefront.layouts.app')

@section('title', 'Thanh Toán | CozaStore')
@section('headerClass', 'header-v4')

@php
    $authCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
@endphp

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <a href="{{ route('storefront.cart') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Giỏ Hàng <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">Thanh Toán</span>
    </div>
</div>

<div class="container p-t-40 p-b-85">
    <div class="row">
        <div class="col-lg-7 p-b-30">
            <h4 class="mtext-109 cl2 p-b-20">Thông Tin Nhận Hàng</h4>

            @if ($authCustomer)
                <p class="stext-110 p-b-10">
                    Đặt hàng với tài khoản <strong>{{ $authCustomer->name }}</strong>
                    (<a href="{{ route('storefront.account.orders') }}" class="cl1">xem đơn hàng của tôi</a>)
                </p>
            @endif

            <form id="checkout-form">
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="customer_name" placeholder="Họ Tên" value="{{ $authCustomer->name ?? '' }}" required>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15"
                        style="width:100%; height:50px;@if($authCustomer) background:#f7f7f7;@endif"
                        type="text" name="customer_phone" placeholder="Số Điện Thoại"
                        value="{{ $authCustomer->phone ?? '' }}" @if($authCustomer) readonly @endif required>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="shipping_address" placeholder="Địa Chỉ Giao Hàng" value="{{ $authCustomer->address ?? '' }}" required>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <textarea class="stext-111 cl8 plh3 p-lr-15 p-tb-10" style="width:100%;"
                        name="note" placeholder="Ghi chú (không bắt buộc)" rows="3"></textarea>
                </div>

                <div id="checkout-errors" class="p-b-10" style="display:none; color:#e04141;"></div>

                <button type="submit" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04">
                    ĐẶT HÀNG
                </button>
            </form>

            <div id="checkout-success" style="display:none;" class="p-t-20">
                <div style="border: 1px solid #b7e4c7; background: #eafaf1; border-radius: 8px; padding: 20px;">
                    <h5 style="color:#1b7a43;">Đặt hàng thành công!</h5>
                    <p>Mã đơn hàng của bạn: <strong id="order-code"></strong></p>
                    <p id="order-discount" style="display:none;">Đã áp dụng khuyến mãi, giảm: <strong id="order-discount-amount"></strong></p>
                    <p>Chúng tôi sẽ liên hệ qua số điện thoại đã cung cấp để xác nhận đơn hàng.</p>
                    <a href="{{ route('storefront.home') }}"
                        class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04"
                        style="display:inline-flex; margin-top: 10px;">Tiếp Tục Mua Sắm</a>
                </div>
            </div>
        </div>

        <div class="col-lg-5 p-b-30">
            <div class="bor10 p-lr-15 p-t-15 p-b-15">
                <h4 class="mtext-109 cl2 p-b-30">ĐƠN HÀNG</h4>
                <div id="checkout-items"></div>

                <div class="flex-w flex-m p-t-20 p-b-10" style="border-top: 1px solid #eee;">
                    <div class="bor8 bg0" style="flex:1; min-width:0;">
                        <input id="promo-code-input" class="stext-111 cl8 plh3 p-lr-15"
                            style="width:100%; height:42px; border:none;" type="text" placeholder="Mã khuyến mãi">
                    </div>
                    <button id="promo-apply-btn" type="button"
                        class="flex-c-m stext-101 cl0 bg3 bor14 hov-btn3 p-lr-15 trans-04"
                        style="margin-left:10px; height:42px; white-space:nowrap;">Áp dụng</button>
                </div>
                <div id="promo-errors" class="p-b-10" style="display:none; color:#e04141; font-size:13px;"></div>
                <div id="promo-success" class="p-b-10" style="display:none; color:#1b7a43; font-size:13px;"></div>

                <div id="promo-breakdown" style="display:none;">
                    <div class="flex-w flex-sb-m" style="padding:4px 0;">
                        <span class="stext-110 cl8">Tạm tính</span>
                        <span id="promo-original-price" class="stext-110 cl4" style="text-decoration: line-through;">0 ₫</span>
                    </div>
                    <div class="flex-w flex-sb-m" style="padding:4px 0;">
                        <span class="stext-110 cl8">Giảm giá</span>
                        <span id="promo-discount-amount" class="stext-110" style="color:#e04141;">-0 ₫</span>
                    </div>
                </div>

                <div class="flex-w flex-t p-t-17 p-b-20" style="border-top: 1px solid #eee; justify-content: space-between;">
                    <div class="size-208"><h4 style="font-weight: bold; color:#e04141;">Tổng</h4></div>
                    <div><h4 id="checkout-total" style="font-weight: bold; color:#e04141;">0 ₫</h4></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('storefront/js/pages/checkout.js') }}"></script>
@endpush
