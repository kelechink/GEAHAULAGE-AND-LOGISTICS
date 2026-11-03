<x-mail::message>
# Your shipment is booked

Thank you for choosing GEA Haulage and Logistics. We have booked your shipment:

<x-mail::table>
| | |
|:--|:--|
| Tracking code | **{{ $shipment->tracking_code }}** |
| Route | {{ $shipment->origin }} to {{ $shipment->destination }} |
| Cargo | {{ $shipment->cargo_description }}@if($shipment->tonnage) ({{ rtrim(rtrim((string) $shipment->tonnage, '0'), '.') }} t)@endif |
@if ($pickup)
| Pickup | {{ $pickup }} |
@endif
@if ($eta)
| Estimated delivery | {{ $eta }} |
@endif
</x-mail::table>

Follow the journey any time with your tracking code. On the tracking page you can also ask for an email at each stage.

<x-mail::button :url="$url">Track your shipment</x-mail::button>

Questions about this shipment? Reply to this email or call us.

GEA Haulage and Logistics
</x-mail::message>
