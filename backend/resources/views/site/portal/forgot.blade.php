@extends('site.layout')
@section('title', 'Reset your portal password')
@section('page_header', '1')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('site.partials.page-head', ['crumb' => 'Customer portal', 'heading' => 'Forgot your password?', 'intro' => 'Enter the email you sign in with and we will send a link to choose a new password.'])
<section class="band">
    <div class="wrap portal-narrow">
        @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        <form class="form" method="POST" action="{{ route('portal.password.email') }}">
            @csrf
            @include('site.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'username', 'required' => true, 'full' => true, 'prefix' => 'pf'])
            <div class="full portal-actions"><button class="btn btn-signal" type="submit">Send the link</button><a href="{{ route('portal.login') }}">Back to sign in</a></div>
        </form>
    </div>
</section>
@endsection
