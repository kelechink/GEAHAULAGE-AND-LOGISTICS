<section class="page-head">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / {{ $crumb }}</nav>
        <h1>{{ $heading }}</h1>
        @isset($intro)<p>{{ $intro }}</p>@endisset
    </div>
</section>
