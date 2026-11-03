@extends('site.layout')
@section('title', 'Careers')
@section('description', 'Drive with GEA. We are hiring truck drivers, drivers’ assistants, fleet mechanics and office staff in Lagos.')
@section('page_header', '1')

@section('content')
@include('site.partials.page-head', ['crumb' => 'Careers', 'heading' => 'Drive with GEA.', 'intro' => $site->careers_intro])
<section class="band" id="careers">
    <div class="wrap grid-2" style="align-items:start">
        <div>
            @forelse ($roles as $role)
                <div class="role"><b>{{ $role->title }}</b><span>{{ $role->description }}</span></div>
            @empty
                <p class="lede">We have no open roles right now. Send your details anyway and we will keep them on file.</p>
            @endforelse
        </div>
        @include('site.partials.careers-form')
    </div>
</section>
@endsection
