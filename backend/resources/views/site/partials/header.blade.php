@php $home = request()->routeIs('home'); $hash = $home ? '' : route('home'); @endphp
<header class="site-header {{ $pageHeader ? 'is-page' : '' }}" data-nav>
    <div class="header-row">
        <a class="brand" href="{{ route('home') }}">
            @include('site.partials.logo', ['variant' => 'white'])
        </a>
        <nav class="nav" aria-label="Main">
            <a class="link" href="{{ $hash }}#about">About</a>
            <a class="link" href="{{ $hash }}#services">Services</a>
            <a class="link" href="{{ $hash }}#fleet">Fleet</a>
            <a class="link" href="{{ $hash }}#why">Why GEA</a>
            <a class="link" href="{{ route('track') }}">Track shipment</a>
            <button class="theme-btn" type="button" data-theme-toggle aria-label="Switch between light and dark mode"><span class="theme-dot" aria-hidden="true"></span><span data-theme-label>Dark</span></button>
            <a class="btn btn-signal" href="{{ $home ? '#quote' : route('quote') }}">Request a quote</a>
            <a class="login-link" href="{{ url('/admin/login') }}">Staff login</a>
            <button class="menu-btn" type="button" data-menu-open aria-expanded="false" aria-controls="mobile-menu">Menu</button>
        </nav>
    </div>
    <div class="progress" data-progress></div>
</header>
<div class="mobile-menu" id="mobile-menu" role="dialog" aria-modal="true" aria-label="Menu" hidden>
    <div class="top">
        @include('site.partials.logo', ['variant' => 'white', 'height' => 48])
        <button class="icon-btn" type="button" data-menu-close aria-label="Close menu">✕</button>
    </div>
    <nav aria-label="Mobile">
        <a href="{{ $hash }}#about">About</a>
        <a href="{{ $hash }}#services">Services</a>
        <a href="{{ $hash }}#fleet">Fleet</a>
        <a href="{{ $hash }}#why">Why GEA</a>
        <a href="{{ route('track') }}">Track shipment</a>
        <a href="{{ $hash }}#faq">FAQ</a>
        <a href="{{ route('careers') }}">Careers</a>
        <a class="quote" href="{{ $home ? '#quote' : route('quote') }}">Request a quote</a>
    </nav>
    <button class="theme-btn" type="button" data-theme-toggle aria-label="Switch between light and dark mode"><span class="theme-dot" aria-hidden="true"></span><span data-theme-label>Dark</span></button>
    <a class="login-link" style="opacity:.6;margin-top:10px" href="{{ url('/admin/login') }}">Staff login</a>
</div>
