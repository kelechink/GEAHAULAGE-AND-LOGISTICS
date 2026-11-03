@extends('site.layout')
@section('title', 'Choose your portal password')
@section('page_header', '1')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('site.partials.page-head', ['crumb' => 'Customer portal', 'heading' => 'Choose your password', 'intro' => 'At least 10 characters, with letters and numbers.'])
<section class="band">
    <div class="wrap portal-narrow">
        <form class="form" method="POST" action="{{ route('portal.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @include('site.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'autocomplete' => 'username', 'required' => true, 'full' => true, 'prefix' => 'pr'])
            @include('site.partials.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'full' => true, 'prefix' => 'pr'])
            @include('site.partials.field', ['name' => 'password_confirmation', 'label' => 'Type it again', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'full' => true, 'prefix' => 'pr'])
            <div class="full"><button class="btn btn-signal" type="submit">Save password</button></div>
        </form>
    </div>
</section>
@endsection
