@extends('site.layout')
@section('title', 'Your account')
@section('page_header', '1')
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('site.partials.page-head', ['crumb' => 'Customer portal', 'heading' => $customer->name, 'intro' => 'Your shipments, proof of delivery, invoices and quotes with GEA.'])
<section class="sec">
    <div class="wrap portal">
        <div class="portal-bar">
            <p>Signed in as <strong>{{ auth()->user()->name }}</strong></p>
            <div class="portal-actions">
                <a class="btn btn-outline" href="{{ route('quote') }}">Request a new quote</a>
                <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="btn btn-outline" type="submit">Sign out</button></form>
            </div>
        </div>

        <div class="portal-tiles">
            <div class="portal-tile"><span>Active shipments</span><strong>{{ $active->count() }}</strong></div>
            <div class="portal-tile"><span>Balance owed</span><strong>{{ $owed }}</strong></div>
            <div class="portal-tile"><span>Open quotes</span><strong>{{ $quotes->count() }}</strong></div>
        </div>

        <h2 class="portal-h">Active shipments</h2>
        @forelse ($active as $s)
            <article class="portal-card">
                <div><strong>{{ $s->origin }} → {{ $s->destination }}</strong><span class="portal-muted">{{ $s->tracking_code }} · {{ $s->cargo_description }}</span></div>
                <div><span class="portal-stage">{{ $s->stage->label() }}</span>@if ($e = $eta($s))<span class="portal-muted">ETA {{ $e }}</span>@endif</div>
                <a class="btn btn-ink" href="{{ route('track.show', $s->tracking_code) }}">Track</a>
            </article>
        @empty
            <p class="portal-muted">No shipments on the road right now.</p>
        @endforelse

        <h2 class="portal-h">Invoices</h2>
        @if ($invoices->isEmpty())
            <p class="portal-muted">No invoices yet.</p>
        @else
            <div class="portal-table" role="region" aria-label="Invoices" tabindex="0"><table>
                <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($invoices as $i)
                    <tr>
                        <td>{{ $i->displayNumber() }}</td><td>{{ $i->issue_date?->format('j M Y') }}</td><td>{{ $i->due_date?->format('j M Y') }}</td>
                        <td>{{ $money($i->total_kobo) }}</td><td>{{ $money($i->balance_kobo) }}</td><td>{{ $i->status->label() }}</td>
                        <td><a href="{{ route('portal.invoice', $i) }}" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="portal-muted">Please use the invoice number as your payment reference.</p>
        @endif

        @if ($quotes->isNotEmpty())
            <h2 class="portal-h">Quotes</h2>
            <div class="portal-table" role="region" aria-label="Quotes" tabindex="0"><table>
                <thead><tr><th>Quote</th><th>Date</th><th>Valid until</th><th>Total</th><th></th></tr></thead>
                <tbody>@foreach ($quotes as $q)<tr><td>{{ $q->number }}</td><td>{{ $q->issue_date?->format('j M Y') }}</td><td>{{ $q->valid_until?->format('j M Y') }}</td><td>{{ $money($q->total_kobo) }}</td><td><a href="{{ route('portal.quote', $q) }}" target="_blank" rel="noopener">PDF</a></td></tr>@endforeach</tbody>
            </table></div>
        @endif

        <h2 class="portal-h">Past shipments</h2>
        @forelse ($past as $s)
            <article class="portal-card">
                <div><strong>{{ $s->origin }} → {{ $s->destination }}</strong><span class="portal-muted">{{ $s->tracking_code }} · {{ $s->delivered_at?->format('j M Y') ?? $s->stage->label() }}</span></div>
                <div>@foreach ($proofLinks($s) as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a> @endforeach</div>
                <a class="btn btn-outline" href="{{ route('track.show', $s->tracking_code) }}">Details</a>
            </article>
        @empty
            <p class="portal-muted">No past shipments yet.</p>
        @endforelse
    </div>
</section>
@endsection
