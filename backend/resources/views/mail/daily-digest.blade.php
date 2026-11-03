<x-mail::message>
# Good morning

{{ $summary }}

@if($money)
**{{ $money }}**
@endif

<x-mail::table>
| | Today |
|:--|--:|
| Active trips | {{ $facts['active_trips'] }} |
| Trips gone quiet | {{ $facts['stale_trips'] }} |
| Delayed trips | {{ count($facts['delayed_trips']) }} |
| Pickups today | {{ $facts['pickups_today'] }} |
| Deliveries expected | {{ $facts['deliveries_expected'] }} |
| Overdue invoices | {{ $facts['overdue_invoices'] }} |
| Documents expiring in 14 days | {{ $facts['expiring_documents'] }} |
| New leads since yesterday | {{ $facts['new_leads'] }} |
| Hot leads (intent 70+) | {{ $facts['hot_leads'] }} |
| Missed follow-ups | {{ $facts['missed_follow_ups'] }} |
</x-mail::table>

@if($facts['delayed_trips'] !== [])
**Delays**
@foreach($facts['delayed_trips'] as $d)
- {{ $d }}
@endforeach
@endif

<x-mail::button :url="$url">Open the dashboard</x-mail::button>
</x-mail::message>
