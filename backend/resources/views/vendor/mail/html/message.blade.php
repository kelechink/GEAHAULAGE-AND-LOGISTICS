@php
    $company = rescue(fn () => app(\App\Settings\CompanySettings::class), null, false);
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ $company?->company_name ?: 'GEA Haulage and Logistics' }}**@if($company?->rc_number) · RC {{ $company->rc_number }}@endif

@if($company?->address){{ $company->address }}@endif
@if($company?->phone) · {{ $company->phone }}@endif

[{{ preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')) }}]({{ config('app.url') }}) · Reliable haulage across Nigeria

© {{ date('Y') }} {{ $company?->company_name ?: 'GEA Haulage and Logistics' }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
