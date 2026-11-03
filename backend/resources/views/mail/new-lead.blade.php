<x-mail::message>
# New quote request

**{{ $lead->contact->name }}**
Phone: {{ $lead->contact->phone }}
@if ($lead->contact->email)
Email: {{ $lead->contact->email }}
@endif

| | |
|---|---|
| Service | {{ $lead->service_interest ?? 'Not given' }} |
| From | {{ $lead->origin ?? 'Not given' }} |
| To | {{ $lead->destination ?? 'Not given' }} |

{{ $lead->cargo }}

Follow up by {{ $lead->next_follow_up_at?->timezone('Africa/Lagos')->format('D j M, g:ia') }}.
</x-mail::message>
