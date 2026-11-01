@extends('site.layout')
@section('title', 'Track a shipment')
@section('description', 'Track your GEA shipment with the tracking code on your booking confirmation.')
@section('page_header', '1')

@section('content')
@include('site.partials.page-head', ['crumb' => 'Track shipment', 'heading' => 'Track your shipment', 'intro' => 'Enter the GEA tracking code from your booking confirmation.'])
<section class="sec">
    <div class="wrap stack" style="gap:28px;max-width:900px;padding-top:clamp(48px,6vw,80px)">
        @include('site.partials.track-form', ['code' => $code])
        @if ($code !== '')
            <div class="notice" role="status">
                We couldn't find <strong>{{ $code }}</strong>. Check the code on your booking confirmation or waybill.
                For help, email <a href="mailto:{{ $site->email_support }}" style="text-decoration:underline">{{ $site->email_support }}</a>
                or call <a href="tel:{{ $phoneTel }}" style="text-decoration:underline">{{ $phoneDisplay }}</a>.
            </div>
        @endif
    </div>
</section>
@endsection
