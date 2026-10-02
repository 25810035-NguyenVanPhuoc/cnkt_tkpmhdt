<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'CozaStore')</title>
<link rel="icon" type="image/png" href="{{ asset('storefront/images/icons/favicon.png') }}">

<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/fonts/font-awesome-4.7.0/css/font-awesome.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/fonts/iconic/css/material-design-iconic-font.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/fonts/linearicons-v1.0.0/icon-font.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/animate/animate.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/css-hamburgers/hamburgers.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/animsition/css/animsition.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/select2/select2.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/slick/slick.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/MagnificPopup/magnific-popup.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/vendor/perfect-scrollbar/perfect-scrollbar.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/css/util.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('storefront/css/main.css') }}">
</head>
<body>

@include('storefront.partials.header', ['headerClass' => trim($__env->yieldContent('headerClass'))])

<main>
@yield('content')
</main>

@include('storefront.partials.footer')

<script src="{{ asset('storefront/vendor/jquery/jquery-3.2.1.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/animsition/js/animsition.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/bootstrap/js/popper.js') }}"></script>
<script src="{{ asset('storefront/vendor/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/select2/select2.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/slick/slick.min.js') }}"></script>
<script src="{{ asset('storefront/js/slick-custom.js') }}"></script>
<script src="{{ asset('storefront/vendor/MagnificPopup/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/isotope/isotope.pkgd.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/sweetalert/sweetalert.min.js') }}"></script>
<script src="{{ asset('storefront/vendor/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('storefront/js/main-vendor.js') }}"></script>

<script src="{{ asset('storefront/js/cart.js') }}"></script>
<script src="{{ asset('storefront/js/layout.js') }}"></script>
@stack('scripts')

</body>
</html>
