@extends('site.layout')
@section('title', 'Privacy policy')
@section('page_header', '1')

@section('content')
{{-- Drafted from PRD 16 (Nigeria Data Protection Act 2023). GEA must review before go-live. --}}
@include('site.partials.page-head', ['crumb' => 'Privacy policy', 'heading' => 'Privacy policy', 'intro' => 'How GEA Haulage and Logistics collects and uses your personal data.'])
<section class="sec" id="privacy">
    <div class="wrap">
        <div class="prose">
            <p>{{ $company->company_name }} (RC {{ $company->rc_number }}), {{ $company->address }}, is responsible for the personal data described here. We handle it in line with the Nigeria Data Protection Act 2023.</p>

            <h2>What we collect</h2>
            <ul>
                <li><strong>Quote requests:</strong> your name, phone number, email address, company, pickup and delivery locations, and cargo details.</li>
                <li><strong>Job applications:</strong> your name, phone number, the role you apply for, your experience, licence class and anything you tell us about yourself.</li>
                <li><strong>Shipment tracking:</strong> the tracking code you enter. We do not ask for any other details to track a shipment.</li>
                <li><strong>Chat assistant:</strong> if you use the chat on our website, the messages you send and any contact details you give. Chats are saved to help our team respond.</li>
            </ul>

            <h2>How we use it</h2>
            <ul>
                <li>To reply to your request, prepare a quote and arrange your shipment.</li>
                <li>To assess job applications.</li>
                <li>To send you updates you asked for, such as shipment updates by email.</li>
                <li>To send marketing only if you have agreed to it. You can withdraw that consent at any time.</li>
            </ul>

            <h2>Who we share it with</h2>
            <p>We do not sell your data. We share it only with the service providers we need to run our business: our website and email hosting, and the AI provider that powers the chat assistant. Before chat messages reach the AI provider, we remove prices, payment and bank details and other customers' information. We share data with authorities when the law requires it.</p>

            <h2>How long we keep it</h2>
            <p>We keep enquiry and customer records for as long as we have a business relationship with you and as long as tax and accounting law requires after that. We keep unsuccessful job applications for up to 12 months.</p>

            <h2>Your rights</h2>
            <p>You can ask us for a copy of your data, ask us to correct or delete it, or object to how we use it. Email <a href="mailto:{{ $site->email_info }}">{{ $site->email_info }}</a> or call {{ $phoneDisplay }}. If you are not satisfied with our answer, you can complain to the Nigeria Data Protection Commission.</p>

            <h2>Cookies and storage</h2>
            <p>Our website uses a session cookie to keep forms secure and remembers your light or dark display choice in your browser. We do not use advertising cookies.</p>

            <h2>Changes</h2>
            <p>We will post any changes on this page. Last updated {{ \Illuminate\Support\Carbon::parse('2026-11-01')->format('j F Y') }}.</p>
        </div>
    </div>
</section>
@endsection
