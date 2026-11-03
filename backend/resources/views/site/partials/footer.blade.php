@php $hash = request()->routeIs('home') ? '' : route('home'); @endphp
<footer class="site-footer">
    <div class="wrap">
        <div class="foot-top">
            <div class="stack" style="gap:24px">
                <span class="logo-light">@include('site.partials.logo', ['variant' => 'blue', 'height' => 96])</span>
                <span class="logo-dark">@include('site.partials.logo', ['variant' => 'white', 'height' => 96])</span>
                <span class="slogan">Moving Goods, Building Trust, Delivering Excellence.</span>
            </div>
            <nav class="foot-nav" aria-label="Footer">
                <a href="{{ $hash }}#about">About</a>
                <a href="{{ $hash }}#services">Services</a>
                <a href="{{ $hash }}#fleet">Fleet</a>
                <a href="{{ $hash }}#why">Why GEA</a>
                <a href="{{ $hash }}#faq">FAQ</a>
                <a href="{{ route('careers') }}">Careers</a>
                <a href="{{ route('track') }}">Track shipment</a>
                <a href="{{ route('quote') }}">Request a quote</a>
                <a href="{{ route('privacy') }}">Privacy policy</a>
                <a href="{{ route('terms') }}">Terms of service</a>
            </nav>
        </div>
        <nav class="foot-svc" aria-label="Services">
            @foreach ($services as $s)
                <a href="{{ route('services.show', $s) }}">{{ $s->short_title }}</a>
            @endforeach
        </nav>
        <div class="outline-word" aria-hidden="true">GEA Haulage</div>
        <div class="foot-base">
            <span>© {{ now()->year }} {{ $company->company_name }}. RC {{ $company->rc_number }}.</span>
            <span>{{ $company->address }}. <a href="tel:{{ $phoneTel }}">{{ $phoneDisplay }}</a>. <a href="mailto:{{ $site->email_info }}">{{ $site->email_info }}</a></span>
        </div>
    </div>
</footer>
