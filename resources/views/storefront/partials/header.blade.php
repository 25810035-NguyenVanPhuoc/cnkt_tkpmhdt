@php
    $headerCategories = \App\Models\Category::where('is_active', true)->orderBy('name')->get();
    $authCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
@endphp

<header class="{{ $headerClass ?? '' }}">
    <div class="container-menu-desktop">
        <div class="top-bar">
            <div class="content-topbar flex-sb-m h-full container">
                <div class="left-top-bar"><marquee>Miễn phí vận chuyển cho đơn hàng tiêu chuẩn trị giá từ 5 triệu</marquee></div>

                <div class="right-top-bar flex-w h-full">
                    <a href="#" class="flex-c-m trans-04 p-lr-25">VN</a>
                    <a href="#" class="flex-c-m trans-04 p-lr-25">EN</a>
                    @if ($authCustomer)
                        <div class="flex-c-m trans-04 p-lr-25 account-menu">
                            <a href="#" class="account-menu-toggle" style="color:inherit;">
                                {{ $authCustomer->name }} <i class="fa fa-angle-down m-l-5"></i>
                            </a>
                            <ul class="sub-menu account-menu-list">
                                <li><a href="{{ route('storefront.account.orders') }}">Đơn hàng của tôi</a></li>
                                <li>
                                    <form method="POST" action="{{ route('storefront.logout') }}" class="account-logout-form">
                                        @csrf
                                        <button type="submit" class="account-logout-btn">Đăng xuất</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('storefront.login') }}" class="flex-c-m trans-04 p-lr-25">Đăng Nhập</a>
                        <a href="{{ route('storefront.register') }}" class="flex-c-m trans-04 p-lr-25">Đăng Ký</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="wrap-menu-desktop">
            <nav class="limiter-menu-desktop container">

                <!-- Logo desktop -->
                <a href="{{ route('storefront.home') }}" class="logo">
                    <img src="{{ asset('storefront/images/icons/logo-01.png') }}" alt="IMG-LOGO">
                </a>

                <!-- Menu desktop -->
                <div class="menu-desktop">
                    <ul class="main-menu">
                        <li class="active-menu"><a href="{{ route('storefront.home') }}">Trang Chủ</a></li>

                        <li><a href="{{ route('storefront.home') }}">Mua Sắm</a>
                            <ul class="sub-menu">
                                @foreach ($headerCategories as $c)
                                    <li><a href="{{ route('storefront.home') }}#cat-{{ $c->slug }}">{{ $c->name }}</a></li>
                                @endforeach
                            </ul>
                        </li>

                        <li class="label1" data-label1="hot"><a href="{{ route('storefront.home') }}#cat-iphone">iPhone</a></li>

                        <li class="label1" data-label1="hot"><a href="{{ route('storefront.home') }}#cat-macbook">MacBook</a></li>

                        <li><a href="#">Blog</a></li>

                        <li><a href="#">Giới Thiệu</a></li>

                        <li><a href="#">Liên Hệ</a></li>
                    </ul>
                </div>

                <!-- Icon header -->
                <div class="wrap-icon-header flex-w flex-r-m">
                    <div class="icon-header-item cl2 hov-cl1 trans-04 p-l-22 p-r-11 icon-header-noti" data-notify="0">
                        <i class="zmdi zmdi-time-restore"></i>
                    </div>
                    <a href="{{ route('storefront.cart') }}"
                        class="icon-header-item cl2 hov-cl1 trans-04 p-l-22 p-r-11 icon-header-noti js-cart-count js-show-cart"
                        data-notify="0">
                        <i class="zmdi zmdi-shopping-cart"></i>
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Header mobile -->
    <div class="wrap-header-mobile">
        <div class="logo-mobile">
            <a href="{{ route('storefront.home') }}"><img src="{{ asset('storefront/images/icons/logo-01.png') }}" alt="IMG-LOGO"></a>
        </div>

        <div class="wrap-icon-header flex-w flex-r-m m-r-15">
            <div class="icon-header-item cl2 hov-cl1 trans-04 p-r-11 icon-header-noti" data-notify="0">
                <i class="zmdi zmdi-time-restore"></i>
            </div>

            <a href="{{ route('storefront.cart') }}" class="dis-block icon-header-item cl2 hov-cl1 trans-04 p-r-11 p-l-10 icon-header-noti js-cart-count js-show-cart" data-notify="0">
                <i class="zmdi zmdi-shopping-cart"></i>
            </a>
        </div>

        <div class="btn-show-menu-mobile hamburger hamburger--squeeze">
            <span class="hamburger-box">
                <span class="hamburger-inner"></span>
            </span>
        </div>
    </div>

    <!-- Menu Mobile -->
    <div class="menu-mobile">
        <ul class="topbar-mobile">
            <li>
                <div class="left-top-bar">
                    Miễn phí vận chuyển cho đơn hàng tiêu chuẩn trị giá từ 5 triệu
                </div>
            </li>

            <li>
                <div class="right-top-bar flex-w h-full">
                    <a href="#" class="flex-c-m p-lr-10 trans-04">VN</a>
                    <a href="#" class="flex-c-m p-lr-10 trans-04">EN</a>
                    @if ($authCustomer)
                        <span class="flex-c-m p-lr-10 trans-04">{{ $authCustomer->name }}</span>
                    @else
                        <a href="{{ route('storefront.login') }}" class="flex-c-m p-lr-10 trans-04">Đăng Nhập</a>
                    @endif
                </div>
            </li>
            @if ($authCustomer)
                <li>
                    <a href="{{ route('storefront.account.orders') }}">Đơn hàng của tôi</a>
                </li>
                <li>
                    <form method="POST" action="{{ route('storefront.logout') }}">
                        @csrf
                        <button type="submit" style="background:none;border:0;width:100%;text-align:left;padding:0;">Đăng xuất</button>
                    </form>
                </li>
            @else
                <li>
                    <a href="{{ route('storefront.register') }}">Đăng Ký</a>
                </li>
            @endif
        </ul>

        <ul class="main-menu-m">
            <li>
                <a href="{{ route('storefront.home') }}">Trang Chủ</a>
            </li>

            <li>
                <a href="{{ route('storefront.home') }}">Mua Sắm</a>
                <ul class="sub-menu-m">
                    @foreach ($headerCategories as $c)
                        <li><a href="{{ route('storefront.home') }}#cat-{{ $c->slug }}">{{ $c->name }}</a></li>
                    @endforeach
                </ul>
                <span class="arrow-main-menu-m">
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </span>
            </li>

            <li>
                <a href="{{ route('storefront.home') }}#cat-iphone">iPhone</a>
            </li>

            <li>
                <a href="{{ route('storefront.home') }}#cat-macbook">MacBook</a>
            </li>

            <li>
                <a href="#">Blog</a>
            </li>

            <li>
                <a href="#">Giới Thiệu</a>
            </li>

            <li>
                <a href="#">Liên Hệ</a>
            </li>
        </ul>
    </div>
</header>
