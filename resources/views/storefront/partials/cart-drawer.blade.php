<style>
    .js-drawer-qty-input::-webkit-outer-spin-button,
    .js-drawer-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .js-drawer-qty-input[type=number] {
        -moz-appearance: textfield;
    }
</style>

<div class="wrap-header-cart js-panel-cart">
    <div class="s-full js-hide-cart"></div>

    <div class="header-cart flex-col-l p-l-20 p-r-20" style="padding-top:15px;">
        <div class="header-cart-title flex-w flex-sb-m p-b-16" style="border-bottom:1px solid #eee;">
            <span class="mtext-103 cl2" style="display:flex; align-items:center;">
                <i class="zmdi zmdi-shopping-cart" style="font-size:20px; margin-right:10px;"></i> Giỏ Hàng
            </span>

            <div class="fs-30 lh-10 cl2 pointer hov-cl1 trans-04 js-hide-cart">
                <i class="zmdi zmdi-close"></i>
            </div>
        </div>

        <div class="w-full" style="background:#eafaf1; color:#1b7a43; font-size:13px; padding:10px 14px; border-radius:6px; margin-top:16px;">
            <i class="zmdi zmdi-truck"></i> Miễn phí vận chuyển cho đơn hàng từ 5 triệu
        </div>

        <div class="header-cart-content flex-w" style="padding-top:16px;">
            <ul class="header-cart-wrapitem w-full" id="cart-drawer-items" style="padding:0;"></ul>

            <div class="w-full" id="cart-drawer-empty" style="display:none; text-align:center; padding:50px 0; color:#999;">
                <i class="zmdi zmdi-shopping-cart" style="font-size:38px; display:block; margin-bottom:10px; color:#ddd;"></i>
                Giỏ hàng đang trống.
            </div>

            <div class="w-full" style="margin-top:auto;">
                <div class="flex-w flex-sb-m" style="border-top:1px solid #eee; padding:18px 0;">
                    <span class="stext-110 cl2" style="font-weight:600;">Tổng cộng</span>
                    <span id="cart-drawer-total" style="font-size:20px; font-weight:700; color:#e04141;">0 ₫</span>
                </div>

                <a href="{{ route('storefront.checkout') }}"
                    class="flex-c-m stext-101 cl0 bg3 bor14 hov-btn3 trans-04 m-b-10"
                    style="width:100%; height:50px;">Thanh Toán</a>
                <a href="{{ route('storefront.cart') }}"
                    class="flex-c-m stext-101 cl2 bor14 trans-04"
                    style="width:100%; height:50px; border:1px solid #222; margin-bottom:10px;">Xem Giỏ Hàng</a>
            </div>
        </div>
    </div>
</div>
