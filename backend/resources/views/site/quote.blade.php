@extends('site.layout')
@section('title', 'Request a quote')
@section('description', 'Request a haulage quote from GEA Haulage and Logistics. Tell us the cargo, route and date and we reply within one working day.')
@section('page_header', '1')

@section('content')
@include('site.partials.page-head', ['crumb' => 'Request a quote', 'heading' => 'Ready to move your goods?', 'intro' => 'Tell us the cargo, the pickup and delivery points and your preferred date. We reply within one working day.'])
<section class="band" id="quote">
    <div class="wrap grid-2" style="align-items:start">
        <div class="stack" style="gap:26px">
            <p class="lede">{{ $site->quote_intro }}</p>
            @include('site.partials.contact-block')
        </div>
        @include('site.partials.quote-form')
    </div>
</section>
@endsection
