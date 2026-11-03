@extends('site.layout')

@section('content')
@php
    $xb = $company->show_cross_border;
    $crops = [
        'fleet-truck' => 'background-size:198% auto;background-position:100% 0%',
        'fleet-boxes' => 'background-size:206% auto;background-position:0% 0%',
    ];
@endphp

{{-- Hero: the one orchestrated motion moment (headline lines rise in, then the intro and actions fade up). --}}
<section class="hero" id="top">
    <div class="hero-media" data-hero>
        @foreach (['hero-truck', 'truck-vehicle', 'people'] as $i => $clip)
            <video data-hero-vid="{{ $i }}" muted playsinline preload="none" @if($i === 0) poster="{{ asset("assets/video/{$clip}-poster.jpg") }}" data-autostart @else data-poster="{{ asset("assets/video/{$clip}-poster.jpg") }}" @endif aria-hidden="true">
                <source src="{{ asset("assets/video/{$clip}.webm") }}" type="video/webm">
                <source src="{{ asset("assets/video/{$clip}.mp4") }}" type="video/mp4">
            </video>
        @endforeach
        <div class="tint"></div>
    </div>
    <div class="hero-shade"></div>
    <div class="hero-inner">
        <h1>
            @foreach ($site->hero_lines as $line)
                <span class="line"><span data-hero-line>{{ $line }}</span></span>
            @endforeach
        </h1>
        <div class="hero-foot" data-hero-fade>
            <p>{{ $site->hero_intro }}</p>
            <div class="hero-actions">
                <div class="playlist">
                    <button class="icon-btn" type="button" data-video-toggle aria-label="Pause background video">❚❚</button>
                    @foreach ([0, 1, 2] as $i)<span class="seg" aria-hidden="true"><span data-seg="{{ $i }}"></span></span>@endforeach
                </div>
                <a class="btn btn-signal" href="#quote">Request a quote</a>
                <a class="btn btn-ghost" href="#services">Our services</a>
            </div>
        </div>
    </div>
</section>

<section class="track-bar" id="track" aria-labelledby="track-title">
    <div class="wrap">
        <div class="track-title" id="track-title"><small>Shipment tracking</small><strong>Track your shipment</strong></div>
        @include('site.partials.track-form')
    </div>
</section>

<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        @foreach (array_merge($site->values, $site->values) as $v)<span>{{ $v['title'] }}</span>@endforeach
    </div>
</div>

<section class="sec" id="about" aria-labelledby="about-title">
    <div class="wrap grid-2" style="align-items:center">
        <div class="stack" style="gap:26px">
            <p class="kicker">About us</p>
            <h2 class="h2" id="about-title">{{ $site->about_heading }}</h2>
            @foreach ($site->about_paragraphs as $p)<p class="lede">{{ $p }}</p>@endforeach
            <p class="quote-line">{{ $site->about_quote }}</p>
        </div>
        <div style="position:relative" data-reveal>
            <div class="media-frame">
                <video data-lazy muted loop playsinline preload="none" data-poster="{{ asset('assets/video/people-poster.jpg') }}" aria-hidden="true">
                    <source src="{{ asset('assets/video/people.webm') }}" type="video/webm">
                    <source src="{{ asset('assets/video/people.mp4') }}" type="video/mp4">
                </video>
                <div class="tint"></div>
            </div>
            <div class="float-card"><b>Safe. Fast. Reliable.</b><span>{{ $company->company_name }}</span></div>
        </div>
    </div>
</section>

<section class="band" aria-labelledby="story-title">
    <div class="wrap grid-2" style="align-items:start">
        <div class="stack sticky" style="gap:26px">
            <p class="kicker" id="story-title">Our story</p>
            <p class="story-lead">{{ $site->story_lead }}</p>
        </div>
        <div class="stack" style="gap:28px">
            @foreach ($site->story_paragraphs as $p)<p class="lede" style="font-size:19px">{{ $p }}</p>@endforeach
            <div class="card" style="margin-top:24px" data-reveal><span class="label">Our mission</span><p>{{ $site->mission }}</p></div>
            <div class="card" data-reveal><span class="label">Our vision</span><p>{{ $site->vision }}</p></div>
        </div>
    </div>
</section>

<section class="sec" id="services" aria-labelledby="services-title">
    <div class="wrap stack" style="gap:64px">
        <div class="grid-2" style="align-items:end;gap:24px 80px">
            <div class="stack">
                <p class="kicker">Our services</p>
                <h2 class="h2" id="services-title">Solutions tailored to your needs.</h2>
            </div>
            <p class="lede">{{ $site->services_intro }}</p>
        </div>
        <div class="grid-2" style="align-items:start;gap:48px 64px">
            <div>
                <div class="svc-list" data-svc-list>
                    @foreach ($services as $i => $s)
                        <a class="svc-item {{ $i === 0 ? 'is-on' : '' }}" href="{{ route('services.show', $s) }}" data-svc="{{ $i }}">
                            <span class="t">{{ $s->title }}</span><span class="dot" aria-hidden="true"></span>
                        </a>
                    @endforeach
                </div>
                <p class="fine" style="margin-top:24px">Specific services are subject to the company's operational capacity and arrangements.</p>
            </div>
            <div class="svc-stage" aria-hidden="true">
                @foreach ($services as $i => $s)
                    <div class="svc-slide {{ $i === 0 ? 'is-on' : '' }}" data-slide="{{ $i }}">
                        @if ($s->isVideo())
                            <video data-lazy muted loop playsinline preload="none" data-poster="{{ asset("assets/video/{$s->media}-poster.jpg") }}">
                                <source src="{{ asset("assets/video/{$s->media}.webm") }}" type="video/webm">
                                <source src="{{ asset("assets/video/{$s->media}.mp4") }}" type="video/mp4">
                            </video>
                        @else
                            <div class="img" style="{{ $crops[$s->media] ?? '' }}"></div>
                        @endif
                    </div>
                @endforeach
                <div class="tint"></div>
                <div class="svc-caption">
                    <b data-svc-title>{{ $services->first()?->title }}</b>
                    <a class="btn btn-signal" data-svc-link href="{{ $services->first() ? route('services.show', $services->first()) : '#' }}" tabindex="-1">View service</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="band" id="fleet" aria-labelledby="fleet-title">
    <div class="wrap stack" style="gap:clamp(40px,5vw,64px)">
        <div class="grid-2" style="align-items:end;gap:24px clamp(40px,6vw,80px)">
            <div class="stack">
                <p class="kicker">Fleet and coverage</p>
                <h2 class="h2" id="fleet-title">{{ $xb ? 'Across Nigeria and West Africa.' : 'Across Nigeria.' }}</h2>
            </div>
            <p class="lede">{{ $xb
                ? 'Our trucks move cargo between states across Nigeria and across borders into West Africa, with capacity from light loads to heavy haulage.'
                : 'Our trucks move cargo from Lagos to states across Nigeria, with capacity from light loads to heavy haulage.' }}</p>
        </div>
        <div class="stats">
            <div class="stat"><b data-count="35">35</b><span>Trucks in our fleet</span></div>
            <div class="stat"><b>5–30 t</b><span>Capacity per truck</span></div>
            <div class="stat"><b>36 + FCT</b><span>{{ $xb ? 'Nigerian states we can reach, plus cross-border West Africa' : 'Nigerian states we can reach from Lagos' }}</span></div>
            <div class="stat"><b data-count="2020">2020</b><span>Year founded. RC {{ $company->rc_number }}</span></div>
        </div>
        <div class="stack" style="gap:16px">
            <div class="map-frame">
                <iframe data-map src="{{ asset('coverage-map.html') }}?xb={{ $xb ? 1 : 0 }}" title="Map of GEA's coverage{{ $xb ? ' across Nigeria and West African corridors' : ' across Nigeria' }}" loading="lazy"></iframe>
            </div>
            <div class="legend">
                <span><i aria-hidden="true"></i>Interstate routes from Lagos</span>
                @if ($xb)<span><i class="xb" aria-hidden="true"></i>Cross-border corridors</span>@endif
                <span style="color:var(--band-muted)">Routes shown are indicative.</span>
            </div>
        </div>
    </div>
</section>

<section class="sec-surface" aria-labelledby="values-title">
    <div class="wrap stack" style="gap:56px">
        <div class="stack">
            <p class="kicker">Our core values</p>
            <h2 class="h2" id="values-title">What we stand for.</h2>
        </div>
        <div class="rows">
            @foreach ($site->values as $v)
                <div class="row"><h3>{{ $v['title'] }}</h3><p>{{ $v['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@if (count($site->credentials) > 0 || $site->show_clients)
<section class="sec" id="credentials" aria-labelledby="cred-title">
    <div class="wrap grid-2" style="padding-top:clamp(64px,8vw,104px);padding-bottom:clamp(64px,8vw,104px);align-items:start">
        <div class="stack">
            <p class="kicker">Registration and credentials</p>
            <h2 class="h2 h2-sm" id="cred-title">A registered Nigerian haulage company.</h2>
        </div>
        <div class="creds">
            @foreach ($site->credentials as $c)
                <div class="cred"><b>{{ $c['title'] }}</b><span>{{ $c['detail'] }}</span></div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="night" id="why" aria-labelledby="why-title">
    <video data-lazy muted loop playsinline preload="none" data-poster="{{ asset('assets/video/truck-vehicle-poster.jpg') }}" aria-hidden="true">
        <source src="{{ asset('assets/video/truck-vehicle.webm') }}" type="video/webm">
        <source src="{{ asset('assets/video/truck-vehicle.mp4') }}" type="video/mp4">
    </video>
    <div class="tint"></div>
    <div class="night-shade"></div>
    <div class="wrap stack" style="position:relative;gap:56px;padding-top:clamp(96px,12vw,160px);padding-bottom:clamp(96px,12vw,160px)">
        <div class="stack" style="max-width:820px">
            <p class="kicker" style="color:var(--signal)">Why GEA</p>
            <h2 class="h2" id="why-title">Why choose GEA Haulage and Logistics?</h2>
            <p class="lede" style="color:#C9CEDA">At GEA Haulage and Logistics, we understand that our customers need a logistics partner they can depend on. We strive to distinguish ourselves through:</p>
        </div>
        <ul class="reasons">
            @foreach ($site->reasons as $r)<li>{{ $r }}</li>@endforeach
        </ul>
        <p class="closing">{{ $site->reasons_closing }}</p>
    </div>
</section>

<section class="sec" aria-labelledby="commit-title">
    <div class="wrap stack" style="max-width:1200px;gap:36px">
        <p class="kicker" id="commit-title">Our commitment</p>
        <p class="commit">{{ $site->commitment_statement }}</p>
        <div class="grid-2" style="gap:24px 64px;padding-top:24px">
            @foreach ($site->commitment_paragraphs as $p)<p class="lede" style="font-size:19px">{{ $p }}</p>@endforeach
        </div>
    </div>
</section>

<section class="sec-surface" id="how" aria-labelledby="how-title">
    <div class="wrap stack" style="gap:clamp(40px,5vw,64px)">
        <div class="grid-2" style="align-items:end;gap:24px clamp(40px,6vw,80px)">
            <div class="stack">
                <p class="kicker">How it works</p>
                <h2 class="h2" id="how-title">From quote to delivery in {{ count($site->steps) === 4 ? 'four' : count($site->steps) }} steps.</h2>
            </div>
            <p class="lede">One point of contact from the first call to the signed delivery note.</p>
        </div>
        <ol class="steps">
            @foreach ($site->steps as $st)<li><h3>{{ $st['title'] }}</h3><p>{{ $st['text'] }}</p></li>@endforeach
        </ol>
    </div>
</section>

<section class="band" id="careers" aria-labelledby="careers-title">
    <div class="wrap grid-2" style="align-items:start;gap:48px clamp(40px,6vw,80px)">
        <div class="stack" style="gap:24px">
            <p class="kicker">Careers</p>
            <h2 class="h2" id="careers-title">Drive with GEA.</h2>
            <p class="lede">{{ $site->careers_intro }}</p>
            <div>
                @foreach ($roles as $role)
                    <div class="role"><b>{{ $role->title }}</b><span>{{ $role->description }}</span></div>
                @endforeach
            </div>
        </div>
        @include('site.partials.careers-form')
    </div>
</section>

<section class="sec" id="faq" aria-labelledby="faq-title">
    <div class="wrap grid-2" style="align-items:start;gap:40px clamp(40px,6vw,80px)">
        <div class="stack">
            <p class="kicker">Questions</p>
            <h2 class="h2" id="faq-title">Frequently asked.</h2>
            <p class="lede" style="font-size:17px">Can't find your answer? Call or WhatsApp us on <a href="tel:{{ $phoneTel }}" style="color:var(--ink);font-weight:600">{{ $phoneDisplay }}</a>.</p>
        </div>
        <div class="faq">
            @foreach ($faqs as $q)
                <details><summary>{{ $q->question }}</summary><p>{{ $q->answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>

<section class="band" id="quote" aria-labelledby="quote-title">
    <div class="wrap grid-2" style="align-items:start;gap:clamp(40px,6vw,72px)">
        <div class="stack" style="gap:26px">
            <p class="kicker">Request a quote</p>
            <h2 class="h2" id="quote-title" style="font-size:clamp(48px,6.4vw,104px);line-height:.9">Ready to move your goods?</h2>
            <p class="lede">{{ $site->quote_intro }}</p>
            @include('site.partials.contact-block')
        </div>
        @include('site.partials.quote-form')
    </div>
</section>
@endsection
