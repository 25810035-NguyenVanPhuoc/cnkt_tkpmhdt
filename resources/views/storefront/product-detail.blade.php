@extends('storefront.layouts.app')

@section('title', $product->name . ' | CozaStore')
@section('headerClass', 'header-v4')

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">{{ $product->name }}</span>
    </div>
</div>

<section class="sec-product-detail bg0 p-t-65 p-b-60">
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-lg-7 p-b-30">
                <div class="p-l-25 p-r-30 p-lr-0-lg">
                    @php
                        $mainImage = $product->images->first();
                        $mainImageUrl = $mainImage ? \Illuminate\Support\Facades\Storage::url($mainImage->path) : asset('storefront/images/icons/logo-01.png');
                        $galleryImages = $product->images->isNotEmpty() ? $product->images : collect([null]);
                    @endphp

                    <div class="wrap-slick3 flex-sb flex-w">
                        <div class="wrap-slick3-dots"></div>
                        <div class="wrap-slick3-arrows flex-sb-m flex-w"></div>

                        <div class="slick3 gallery-lb">
                            @foreach ($galleryImages as $image)
                                @php
                                    $imgUrl = $image ? \Illuminate\Support\Facades\Storage::url($image->path) : asset('storefront/images/icons/logo-01.png');
                                @endphp
                                <div class="item-slick3" data-thumb="{{ $imgUrl }}">
                                    <div class="wrap-pic-w pos-relative">
                                        <img src="{{ $imgUrl }}" alt="IMG-PRODUCT">
                                        <a class="flex-c-m size-108 how-pos1 bor0 fs-16 cl10 bg0 hov-btn3 trans-04" href="{{ $imgUrl }}">
                                            <i class="fa fa-expand"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-5 p-b-30">
                <div class="p-r-50 p-t-5 p-lr-0-lg">
                    <h4 class="mtext-105 cl2 p-b-14" style="font-size: 33px;">{{ $product->name }}</h4>

                    <span class="mtext-106 cl2" style="color: #0066CC; font-weight: bold; font-size: 25px;">
                        {{ number_format($product->sale_price, 0, ',', '.') }} ₫
                    </span>

                    <div class="promo mt-4">
                        <h1>Khuyến mãi trị giá 100.000 ₫</h1>
                        <p>Giá và khuyến mãi dự kiến áp dụng đến 23:00</p>
                        <ul>
                            <li>- Tặng miếng dán kính iPhone</li>
                            <li>- Thu cũ Đổi mới: Giảm đến 2 triệu (Tuỳ model máy cũ, Không kèm thanh toán qua cổng online, mua kèm)</li>
                            <li>- Vòng quay may mắn: Giảm 100.000đ - 500.000đ (Chỉ áp dụng tại siêu thị; Không kèm Thu cũ Đổi mới)</li>
                            <li>- Hoàn tiền nếu ở đâu rẻ hơn (Trong vòng 7 ngày; chỉ áp dụng tại siêu thị)</li>
                        </ul>
                    </div>

                    <div class="p-t-20">
                        @unless (in_array($product->category->slug, ['am-thanh', 'phu-kien']))
                            <div class="flex-w p-b-10">
                                <div class="size-203 flex-c-m respon6" style="font-family: sans-serif;">Dung Lượng</div>
                                <div class="size-2004 respon6-next">
                                    <select class="form-control" style="width: 100%; height: 45px;">
                                        <option value="128G">128G</option>
                                        <option value="256G">256G</option>
                                        <option value="512G">512G</option>
                                        <option value="1TB">1TB</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex-w p-b-10">
                                <div class="size-203 flex-c-m respon6" style="font-family: sans-serif;">Màu Sắc</div>
                                <div class="size-2004 respon6-next">
                                    <select class="form-control" style="width: 100%; height: 45px;">
                                        <option value="Black">Black</option>
                                        <option value="Gold">Gold</option>
                                        <option value="Silver">Silver</option>
                                        <option value="Purple">Purple</option>
                                        <option value="Titan">Titan</option>
                                    </select>
                                </div>
                            </div>
                        @endunless

                        <div class="flex-w p-b-10">
                            <div class="size-203 flex-c-m respon6" style="font-family: sans-serif;">Số Lượng</div>
                            <div class="size-204 flex-w flex-m respon6-next">
                                <div class="wrap-num-product flex-w m-r-20 m-tb-10">
                                    <div class="btn-num-product-down cl8 hov-btn3 trans-04 flex-c-m js-qty-down">
                                        <i class="fs-16 zmdi zmdi-minus"></i>
                                    </div>
                                    <input id="product-qty" style="font-size: 19px;" class="mtext-104 cl3 txt-center num-product"
                                        type="number" min="1" value="1">
                                    <div class="btn-num-product-up cl8 hov-btn3 trans-04 flex-c-m js-qty-up">
                                        <i class="fs-16 zmdi zmdi-plus"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-t-20 flex-w">
                        <button id="buy-now-btn"
                            data-id="{{ $product->id }}"
                            data-name="{{ $product->name }}"
                            data-price="{{ $product->sale_price }}"
                            data-image="{{ $mainImageUrl }}"
                            class="flex-c-m stext-101 cl0 size-101 bg11 bor1 hov-btn1 p-lr-15 trans-04 m-r-10">
                            Mua Ngay
                        </button>
                        <button id="add-to-cart-btn"
                            data-id="{{ $product->id }}"
                            data-name="{{ $product->name }}"
                            data-price="{{ $product->sale_price }}"
                            data-image="{{ $mainImageUrl }}"
                            class="flex-c-m stext-101 cl0 size-101 bg1 bor1 hov-btn1 p-lr-15 trans-04">
                            Thêm Vào Giỏ Hàng
                        </button>
                    </div>

                    @if ($product->description)
                        <div class="p-t-40">
                            <h5 class="p-b-10">Mô Tả</h5>
                            <p class="stext-102 cl6">{{ $product->description }}</p>
                        </div>
                    @endif

                    <div class="p-t-20">
                        <span class="stext-107 cl6">Danh mục: {{ $product->category->name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('storefront/js/pages/product-detail.js') }}"></script>
@endpush
