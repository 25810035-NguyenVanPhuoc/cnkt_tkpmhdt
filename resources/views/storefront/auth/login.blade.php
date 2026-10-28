@extends('storefront.layouts.app')

@section('title', 'Đăng Nhập | CozaStore')
@section('headerClass', 'header-v4')

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">Đăng Nhập</span>
    </div>
</div>

<div class="container p-t-40 p-b-85">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7 p-b-30">
            <h4 class="mtext-109 cl2 p-b-20">Đăng Nhập</h4>

            @if (session('status'))
                <div class="p-b-15" style="color:#1b7a43;">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="p-b-15" style="color:#e04141;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.login.submit') }}">
                @csrf
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="phone" value="{{ old('phone') }}" placeholder="Số Điện Thoại" required autofocus>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="password" name="password" placeholder="Mật Khẩu" required>
                </div>

                <button type="submit" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04">
                    ĐĂNG NHẬP
                </button>
            </form>

            <p class="p-t-20 stext-110">
                Chưa có tài khoản? <a href="{{ route('storefront.register') }}" class="cl1">Đăng ký ngay</a>
            </p>
        </div>
    </div>
</div>
@endsection
