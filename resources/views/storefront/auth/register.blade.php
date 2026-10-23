@extends('storefront.layouts.app')

@section('title', 'Đăng Ký | CozaStore')
@section('headerClass', 'header-v4')

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">Đăng Ký</span>
    </div>
</div>

<div class="container p-t-40 p-b-85">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7 p-b-30">
            <h4 class="mtext-109 cl2 p-b-20">Tạo Tài Khoản</h4>

            @if ($errors->any())
                <div class="p-b-15" style="color:#e04141;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.register.submit') }}">
                @csrf
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="name" value="{{ old('name') }}" placeholder="Họ Tên" required autofocus>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="phone" value="{{ old('phone') }}" placeholder="Số Điện Thoại" required>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="email" name="email" value="{{ old('email') }}" placeholder="Email (không bắt buộc)">
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="text" name="address" value="{{ old('address') }}" placeholder="Địa Chỉ (không bắt buộc)">
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="password" name="password" placeholder="Mật Khẩu" required>
                </div>
                <div class="bor8 bg0 m-b-12">
                    <input class="stext-111 cl8 plh3 size-111 p-lr-15" style="width:100%; height:50px;"
                        type="password" name="password_confirmation" placeholder="Nhập Lại Mật Khẩu" required>
                </div>

                <button type="submit" class="flex-c-m stext-101 cl0 size-116 bg3 bor14 hov-btn3 p-lr-15 trans-04">
                    ĐĂNG KÝ
                </button>
            </form>

            <p class="p-t-20 stext-110">
                Đã có tài khoản? <a href="{{ route('storefront.login') }}" class="cl1">Đăng nhập</a>
            </p>
        </div>
    </div>
</div>
@endsection
