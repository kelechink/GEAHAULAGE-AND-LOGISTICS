<x-mail::message>
Dear {{ $invoice->customer->name }},

@switch($kind)
@case('due_soon')
This is a reminder that invoice **{{ $invoice->displayNumber() }}** for **{{ $balance }}** is due on {{ $invoice->due_date?->format('j F Y') }}.
@break
@case('due_today')
Invoice **{{ $invoice->displayNumber() }}** for **{{ $balance }}** is due today.
@break
@case('overdue')
Invoice **{{ $invoice->displayNumber() }}** was due on {{ $invoice->due_date?->format('j F Y') }}. **{{ $balance }}** is still outstanding.
@break
@default
Please find attached invoice **{{ $invoice->displayNumber() }}** for **{{ $total }}**, due on {{ $invoice->due_date?->format('j F Y') }}.
@endswitch

Please use the invoice number as the payment reference. If you have already paid, thank you, and please send us the payment details so we can match it.

Questions? Reply to this email or call 0816 216 3235.

GEA Haulage and Logistics
</x-mail::message>
