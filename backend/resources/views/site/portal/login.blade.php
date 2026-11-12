@extends('site.layout')
@section('title', 'Customer portal sign in')
@section('page_header', '1')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('site.partials.page-head', ['crumb' => 'Customer portal', 'heading' => 'Customer portal', 'intro' => 'Follow your shipments, see proof of delivery and download your invoices.'])
<section class="band">
    <div class="wrap portal-narrow">
        @if ($staffName)<p class="notice" role="status">You are signed in to the staff back office as {{ $staffName }}. Signing in here as a customer signs you out of the back office. <a href="{{ url('/admin') }}">Back to the back office</a></p>@endif
        @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        <form class="form" method="POST" action="{{ route('portal.login.attempt') }}">
            @csrf
            @include('site.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'username', 'required' => true, 'full' => true, 'prefix' => 'pl'])
            @include('site.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true, 'full' => true, 'prefix' => 'pl'])
            <label class="portal-check full"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
            <div class="full portal-actions">
                <button class="btn btn-signal" type="submit">Sign in</button>
                <a href="{{ route('portal.password.request') }}">Forgot password?</a>
            </div>
        </form>
        <p class="portal-muted">No account yet? Ask GEA to invite you: {{ $site->email_support }}. Just want to follow one delivery? <a href="{{ route('track') }}">Track a shipment</a> with its code.</p>
    </div>
</section>
@endsection
