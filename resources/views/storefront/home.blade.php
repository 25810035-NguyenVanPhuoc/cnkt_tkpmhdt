@extends('storefront.layouts.app')

@section('title', 'CozaStore | Trang Chủ')

@section('content')

{{-- Slider (port từ user/index.html — ảnh gốc hotlink shopdunk.com đổi sang ảnh cục bộ đã copy từ CozaStore) --}}
<section class="section-slide">
    <div class="wrap-slick1">
        <div class="slick1">
            <div class="item-slick1" style="background-image: url({{ asset('storefront/images/xa-lo-iphone-11-pro-max-2.jpg') }});"></div>
            <div class="item-slick1" style="background-image: url({{ asset('storefront/images/8ae0906e-nen_mua_macbook_nao_thumbnail-1024x678.jpg') }});"></div>
            <div class="item-slick1" style="background-image: url({{ asset('storefront/images/vua-phu-kien-bao-da-iphone-da-that-cao-cap-chinh-hang.jpg') }});"></div>
        </div>
    </div>
</section>

{{-- Banner (port từ user/index.html .sec-banner .block1) --}}
<div class="sec-banner bg0 p-t-80 p-b-50">
    <div class="container">
        <div class="row">
            @foreach ($categories->take(3) as $bannerCategory)
                @php
                    $bannerProduct = $bannerCategory->bestsellerProducts->first();
                    $bannerImage = $bannerProduct?->images->first();
                    $bannerImageUrl = $bannerImage ? \Illuminate\Support\Facades\Storage::url($bannerImage->path) : asset('storefront/images/icons/logo-01.png');
                @endphp
                <div class="col-md-6 col-xl-4 p-b-30 m-lr-auto">
                    <div class="block1 wrap-pic-w">
                        <img src="{{ $bannerImageUrl }}" alt="IMG-BANNER">
                        <a href="#cat-{{ $bannerCategory->slug }}"
                            class="block1-txt ab-t-l s-full flex-col-l-sb p-lr-38 p-tb-34 trans-03 respon3">
                            <div class="block1-txt-child1 flex-col-l">
                                <span class="block1-name ltext-102 trans-04 p-b-8">{{ $bannerCategory->name }}</span>
                                <span class="block1-info stext-102 trans-04">Khám Phá Ngay</span>
                            </div>
                            <div class="block1-txt-child2 p-b-4 trans-05">
                                <div class="block1-link stext-101 cl0 trans-09">Mua Ngay</div>
                            </div>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Home (port từ user/index.html — mỗi category 1 section .sec-product với Tab01) --}}
@foreach ($categories as $category)
<section class="sec-product bg0 p-t-30 p-b-30" id="cat-{{ $category->slug }}">
    <div class="container">
        <div class="p-b-22">
            <h3 class="ltext-102 cl5 txt-center respon1">{{ $category->name }}</h3>
        </div>

        <!--Tab01-->
        <div class="tab01">
            <!--Nav tabs-->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item p-b-10">
                    <a class="nav-link active" data-toggle="tab" href="#cat-{{ $category->slug }}-new" role="tab">Sản Phẩm Mới</a>
                </li>
                <li class="nav-item p-b-10">
                    <a class="nav-link" data-toggle="tab" href="#cat-{{ $category->slug }}-cheap" role="tab">Giá Tốt Nhất</a>
                </li>
                <li class="nav-item p-b-10">
                    <a class="nav-link" data-toggle="tab" href="#cat-{{ $category->slug }}-best" role="tab">Bán Chạy</a>
                </li>
            </ul>

            <!--Tab panes-->
            <div class="tab-content p-t-20">
                @foreach ([
                    'new' => $category->newestProducts,
                    'cheap' => $category->cheapestProducts,
                    'best' => $category->bestsellerProducts,
                ] as $tabKey => $products)
                    <div class="tab-pane fade {{ $tabKey === 'new' ? 'show active' : '' }}" id="cat-{{ $category->slug }}-{{ $tabKey }}" role="tabpanel">
                        <div class="container-fluid">
                            <div class="row">
                                @forelse ($products as $product)
                                    @php
                                        $image = $product->images->first();
                                        $imageUrl = $image ? \Illuminate\Support\Facades\Storage::url($image->path) : asset('storefront/images/icons/logo-01.png');
                                    @endphp
                                    <div class="col-sm-3 col-md-3 col-lg-3 p-b-30 isotope-item">
                                        <!-- Block2 -->
                                        <div class="block2">
                                            <div class="block2-pic hov-img0">
                                                <img alt="IMG-PRODUCT" src="{{ $imageUrl }}">
                                                <a href="{{ route('storefront.product', $product->slug) }}"
                                                    class="block2-btn flex-c-m stext-103 cl2 size-102 bg0 bor2 hov-btn1 p-lr-15 trans-04">
                                                    Xem Chi Tiết
                                                </a>
                                            </div>

                                            <div class="block2-txt flex-w flex-t p-t-14">
                                                <div class="block2-txt-child1 flex-col-l">
                                                    <div class="card-name">
                                                        <h5>
                                                            <a class="js-name-b2" href="{{ route('storefront.product', $product->slug) }}">{{ $product->name }}</a>
                                                        </h5>
                                                    </div>
                                                    <div style="color: #0066CC; font-weight: bolder; margin: 5px; font-size: 17px;">
                                                        {{ number_format($product->sale_price, 0, ',', '.') }} ₫
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="p-l-15 p-b-30">Chưa có sản phẩm trong danh mục này.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Load more -->
                <div class="flex-c-m flex-w w-full p-t-45">
                    <a href="{{ route('storefront.home') }}#cat-{{ $category->slug }}"
                        class="flex-c-m stext-101 cl5 size-103 bg2 bor1 hov-btn1 p-lr-15 trans-04">
                        Xem Tất Cả {{ $category->name }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endforeach
@endsection
