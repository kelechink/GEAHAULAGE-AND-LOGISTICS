@extends('site.layout')
@section('title', 'Tracking '.$data['code'])
@section('description', 'Live tracking for GEA shipment '.$data['code'].'.')
@section('page_header', '1')

@push('head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@section('content')
<section class="page-head">
    <div class="wrap" style="padding-bottom:clamp(32px,4vw,48px)">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('track') }}">Track shipment</a> / {{ $data['code'] }}</nav>
        <h1 style="font-size:clamp(36px,5vw,72px)">{{ $data['origin'] }} → {{ $data['destination'] }}</h1>
        <p>Tracking code <strong style="color:#fff">{{ $data['code'] }}</strong>@if ($data['truck']) · Truck {{ $data['truck'] }}@endif</p>
    </div>
</section>

<section class="sec" id="tracking" data-tracking data-code="{{ $data['code'] }}" data-updates="{{ route('api.track.updates', $data['code']) }}">
    <div class="wrap trk" style="padding-top:clamp(32px,4vw,56px)">
        <div class="trk-status">
            <div>
                <span class="trk-stage" data-stage>{{ $data['stage_label'] }}</span>
                <p class="trk-meta"><span data-updated>{{ $data['updated_ago'] ?? 'Waiting for the first update' }}</span><span data-eta>{{ $data['eta'] ? ' · '.$data['eta'] : '' }}</span></p>
                <p class="trk-delay" data-delay @if (! $data['delay_reason']) hidden @endif>Delayed: <span>{{ $data['delay_reason'] }}</span></p>
            </div>
            <div class="trk-bar" role="progressbar" aria-label="Journey progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $data['progress'] }}" data-progressbar><span style="width:{{ $data['progress'] }}%"></span></div>
        </div>

        <div class="trk-grid">
            <div class="trk-map" data-map-el role="img" aria-label="Map of the route from {{ $data['origin'] }} to {{ $data['destination'] }}"></div>
            <div>
                <h2 class="trk-h">Journey</h2>
                <ol class="trk-timeline" data-timeline>
                    @foreach (array_reverse($data['events']) as $e)
                        <li data-event="{{ $e['id'] }}"><b>{{ $e['label'] }}</b>@if ($e['place'])<span>{{ $e['place'] }}</span>@endif<time datetime="{{ $e['at'] }}">{{ $e['at_label'] }}</time>@if ($e['note'])<p>{{ $e['note'] }}</p>@endif</li>
                    @endforeach
                </ol>

                <div class="trk-sub">
                    <h2 class="trk-h">Get updates by email</h2>
                    @if (session('subscribed'))
                        <p role="status">You'll get an email each time this shipment moves to a new stage.</p>
                    @else
                        <form method="post" action="{{ route('track.subscribe', $data['code']) }}" class="trk-subform" novalidate>
                            @csrf
                            <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <label class="sr-only" for="sub-email">Email address</label>
                            <input id="sub-email" name="email" type="email" autocomplete="email" placeholder="you@company.com" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="sub-err" @enderror>
                            <button class="btn btn-signal" type="submit">Get updates</button>
                        </form>
                        @error('email')<p class="err" id="sub-err" style="color:#B4232C;font-size:14px;margin-top:6px">{{ $message }}</p>@enderror
                    @endif
                </div>
                <p class="fine" style="margin-top:20px">Questions about this delivery? Call or WhatsApp <a href="tel:{{ $phoneTel }}" style="text-decoration:underline">{{ $phoneDisplay }}</a> or email <a href="mailto:{{ $site->email_support }}" style="text-decoration:underline">{{ $site->email_support }}</a>.</p>
            </div>
        </div>
    </div>
</section>
<script type="application/json" id="trk-data">@json($data)</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
<script src="{{ asset('js/track.js') }}?v={{ filemtime(public_path('js/track.js')) }}" defer></script>
@endpush
