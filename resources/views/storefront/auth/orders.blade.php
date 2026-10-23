@extends('storefront.layouts.app')

@section('title', 'Đơn Hàng Của Tôi | CozaStore')
@section('headerClass', 'header-v4')

@php
    $statusLabel = [
        'pending' => 'Chờ xử lý',
        'confirmed' => 'Đã xác nhận',
        'delivering' => 'Đang giao',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã huỷ',
    ];
@endphp

@section('content')
<div class="container">
    <div class="bread-crumb flex-w p-l-25 p-r-15 p-t-30 p-lr-0-lg">
        <a href="{{ route('storefront.home') }}" class="stext-109 cl8 hov-cl1 trans-04">
            Trang Chủ <i class="fa fa-angle-right m-l-9 m-r-10" aria-hidden="true"></i>
        </a>
        <span class="stext-109 cl4">Đơn Hàng Của Tôi</span>
    </div>
</div>

<div class="container p-t-40 p-b-85">
    <h4 class="mtext-109 cl2 p-b-30">Đơn Hàng Của Tôi</h4>

    @if ($orders->isEmpty())
        <p class="stext-110">Bạn chưa có đơn hàng nào. <a href="{{ route('storefront.home') }}" class="cl1">Mua sắm ngay</a></p>
    @else
        <div style="overflow-x:auto;">
            <table class="w-100" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee; text-align:left;">
                        <th class="p-tb-10">Mã đơn</th>
                        <th class="p-tb-10">Sản phẩm</th>
                        <th class="p-tb-10">Trạng thái</th>
                        <th class="p-tb-10">Tổng tiền</th>
                        <th class="p-tb-10">Ngày đặt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr style="border-bottom: 1px solid #f1f1f1;">
                            <td class="p-tb-12" style="font-family: monospace;">{{ $order->code }}</td>
                            <td class="p-tb-12">
                                @foreach ($order->items as $item)
                                    <div>{{ $item->product->name ?? '—' }} x{{ $item->quantity }}</div>
                                @endforeach
                            </td>
                            <td class="p-tb-12">
                                <span style="background:#f1f1f1; border-radius:4px; padding:2px 8px; font-size:12px;">
                                    {{ $statusLabel[$order->status->value] ?? $order->status->value }}
                                </span>
                            </td>
                            <td class="p-tb-12" style="font-weight:600;">{{ number_format($order->grand_total) }} đ</td>
                            <td class="p-tb-12" style="color:#888;">{{ $order->order_date?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-t-30">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
