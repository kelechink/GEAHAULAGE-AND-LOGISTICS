<x-mail::message>
# {{ $event->to_stage?->label() }}

Your shipment **{{ $shipment->tracking_code }}** from {{ $shipment->origin }} to {{ $shipment->destination }} is now **{{ mb_strtolower($event->to_stage?->label() ?? '') }}**.
@if ($event->place_label)
Location: {{ $event->place_label }}.
@endif
@if ($event->note && $event->is_public)

{{ $event->note }}
@endif

<x-mail::button :url="$url">Track your shipment</x-mail::button>

GEA Haulage and Logistics
</x-mail::message>
