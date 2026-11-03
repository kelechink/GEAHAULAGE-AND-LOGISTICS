@extends('site.layout')
@section('title', 'Terms of service')
@section('page_header', '1')

@section('content')
{{-- Draft website terms. GEA must review before go-live; haulage contracts are governed by each booking's written terms. --}}
@include('site.partials.page-head', ['crumb' => 'Terms of service', 'heading' => 'Terms of service', 'intro' => 'The terms that apply when you use this website.'])
<section class="sec" id="terms">
    <div class="wrap">
        <div class="prose">
            <p>This website is run by {{ $company->company_name }} (RC {{ $company->rc_number }}). By using it, you agree to these terms.</p>

            <h2>Quotes and bookings</h2>
            <p>Sending a quote request does not create a booking. A booking is confirmed only when we agree the price, schedule and terms with you in writing. Each haulage job is governed by the terms in its quotation or booking confirmation.</p>

            <h2>Services</h2>
            <p>Service descriptions on this website are a general guide. Specific services are subject to the company's operational capacity and arrangements.</p>

            <h2>Shipment tracking</h2>
            <p>Tracking information, including locations and estimated arrival times, is provided for convenience and may not be exact. Contact us if you need confirmed information.</p>

            <h2>Using this website</h2>
            <p>Do not misuse the website, send false information through its forms, or try to access parts of it that are not meant for you. The content, logo and design belong to {{ $company->company_name }} and may not be copied without permission.</p>

            <h2>Liability</h2>
            <p>We work to keep this website accurate and available, but we cannot promise it will always be error-free or uninterrupted. Our liability for any haulage job is set by the terms of that job's booking.</p>

            <h2>Law</h2>
            <p>These terms are governed by the laws of the Federal Republic of Nigeria.</p>

            <h2>Contact</h2>
            <p>{{ $company->address }}. <a href="tel:{{ $phoneTel }}">{{ $phoneDisplay }}</a>. <a href="mailto:{{ $site->email_info }}">{{ $site->email_info }}</a>.</p>
        </div>
    </div>
</section>
@endsection
