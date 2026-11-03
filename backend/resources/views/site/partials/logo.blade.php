@props(['variant' => 'white', 'height' => 52])
@php $file = 'assets/img/logo-'.$variant.'.png'; @endphp
@if (file_exists(public_path($file)))
    <img src="{{ asset($file) }}" alt="{{ $company->company_name }}" height="{{ $height }}" style="height:{{ $height }}px;width:auto">
@else
    <span class="wordmark"><b>GEA</b> <span>Haulage and Logistics</span></span>
@endif
