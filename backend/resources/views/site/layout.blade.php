@php
    $title = trim($__env->yieldContent('title')) ?: null;
    $description = trim($__env->yieldContent('description')) ?: null;
    $pageHeader = trim($__env->yieldContent('page_header')) === '1';
    $pageTitle = $title ? $title.' | '.$company->company_name : $company->company_name.' | Haulage and trucking in Lagos, Nigeria';
    $metaDescription = $description ?? 'GEA Haulage and Logistics moves goods across Nigeria with a fleet of 35 trucks from 5 to 30 tonnes: general haulage, containers, petroleum products, bulk cargo, heavy equipment, warehousing and freight forwarding.';
@endphp
<!doctype html>
<html lang="en-NG" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $company->company_name }}">
    <meta property="og:title" content="{{ $title ?? $company->company_name }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('assets/video/hero-truck-poster.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0B1430">
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">
    <script>
        // Apply the saved theme before first paint, so dark-mode visitors see no flash.
        (function () { try { var t = localStorage.getItem('gea-theme'); if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t); } catch (e) {} document.documentElement.classList.add('js'); })();
    </script>
    <link rel="preload" href="{{ asset('fonts/barlow-condensed-latin-800-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/barlow-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('assets/video/hero-truck-poster.jpg') }}" as="image" fetchpriority="high">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    @include('site.partials.jsonld')
    @stack('head')
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
@include('site.partials.header', ['pageHeader' => $pageHeader])
<main id="main">
    @yield('content')
</main>
@include('site.partials.footer')
<a class="wa-float" href="https://wa.me/{{ $company->whatsapp }}" target="_blank" rel="noopener"><i aria-hidden="true"></i>WhatsApp us</a>
@include('site.partials.assistant')
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
<script src="{{ asset('js/assistant.js') }}?v={{ filemtime(public_path('js/assistant.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
