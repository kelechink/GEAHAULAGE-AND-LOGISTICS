@extends('site.layout')
@section('title', $service->title)
@section('description', $service->summary)
@section('page_header', '1')

@section('content')
@include('site.partials.page-head', ['crumb' => 'Services', 'heading' => $service->title, 'intro' => $service->summary])

<section class="sec">
    <div class="wrap grid-2" style="align-items:start">
        <div class="stack" style="gap:26px">
            <div class="prose">
                @foreach (preg_split('/\n\s*\n/', trim($service->body)) ?: [] as $para)<p>{{ $para }}</p>@endforeach
            </div>
            @if (!empty($service->highlights))
                <ul class="ticks">
                    @foreach ($service->highlights as $h)<li>{{ $h }}</li>@endforeach
                </ul>
            @endif
            <p class="fine">Specific services are subject to the company's operational capacity and arrangements.</p>
            <div class="hero-actions">
                <a class="btn btn-signal" href="#quote">Request a quote</a>
                <a class="btn btn-outline" href="https://wa.me/{{ $company->whatsapp }}?text={{ rawurlencode('Hello GEA, I would like a quote for '.$service->short_title.'.') }}" target="_blank" rel="noopener">Ask on WhatsApp</a>
            </div>
        </div>
        <div class="media-frame" style="aspect-ratio:4/3" aria-hidden="true">
            @if ($service->isVideo())
                <video data-lazy muted loop playsinline preload="none" poster="{{ asset("assets/video/{$service->media}-poster.jpg") }}">
                    <source src="{{ asset("assets/video/{$service->media}.webm") }}" type="video/webm">
                    <source src="{{ asset("assets/video/{$service->media}.mp4") }}" type="video/mp4">
                </video>
            @else
                <div style="position:absolute;inset:0;background-image:url({{ asset('assets/img/fleet.webp') }});background-repeat:no-repeat;{{ $service->media === 'fleet-boxes' ? 'background-size:206% auto;background-position:0% 0%' : 'background-size:198% auto;background-position:100% 0%' }}"></div>
            @endif
            <div class="tint"></div>
        </div>
    </div>
</section>

<section class="band" id="quote" aria-labelledby="quote-title">
    <div class="wrap grid-2" style="align-items:start">
        <div class="stack" style="gap:26px">
            <p class="kicker">Request a quote</p>
            <h2 class="h2 h2-sm" id="quote-title">Get a price for {{ mb_strtolower($service->short_title) }}.</h2>
            <p class="lede">Tell us the cargo, the pickup and delivery points and your preferred date. We reply within one working day.</p>
            @include('site.partials.contact-block')
        </div>
        @include('site.partials.quote-form', ['selectedService' => $service->title])
    </div>
</section>

@if ($others->isNotEmpty())
<section class="sec-surface" aria-labelledby="others-title">
    <div class="wrap stack" style="gap:32px;padding-top:clamp(56px,7vw,96px);padding-bottom:clamp(56px,7vw,96px)">
        <h2 class="h2 h2-sm" id="others-title">Other services</h2>
        <div class="svc-list">
            @foreach ($others as $o)
                <a class="svc-item" href="{{ route('services.show', $o) }}"><span class="t">{{ $o->title }}</span><span class="dot" aria-hidden="true"></span></a>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
