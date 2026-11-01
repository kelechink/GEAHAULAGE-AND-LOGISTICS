@php
    $d = $this->getData();
    $hour = (int) now()->timezone('Africa/Lagos')->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $statusIcon = ['critical' => 'heroicon-s-exclamation-circle', 'serious' => 'heroicon-s-exclamation-triangle', 'warning' => 'heroicon-s-clock', 'good' => 'heroicon-s-check-circle'];
@endphp
<x-filament-widgets::widget>
<div class="gdb" wire:ignore>
    <header class="gdb-head">
        <div>
            <h2 class="gdb-hello">{{ $greeting }}, {{ \Illuminate\Support\Str::of(auth()->user()?->name)->before(' ') }}</h2>
            <p class="gdb-date">{{ now()->timezone('Africa/Lagos')->format('l j F Y') }} · GEA Haulage and Logistics</p>
        </div>
    </header>

    @if (count($d['kpis']))
    <section class="gdb-kpis" aria-label="Key figures">
        @foreach ($d['kpis'] as $k)
            <article class="gdb-kpi" style="--chip: var(--s{{ $k['slot'] }})">
                <span class="gdb-chip" aria-hidden="true">@svg($k['icon'], 'gdb-chip-icon')</span>
                <div class="gdb-kpi-body">
                    <p class="gdb-kpi-label">{{ $k['label'] }}</p>
                    <p class="gdb-kpi-value" @isset($k['full']) title="{{ $k['full'] }}" @endisset>{{ $k['value'] }}@isset($k['full'])<span class="sr-only"> ({{ $k['full'] }})</span>@endisset</p>
                    <p class="gdb-kpi-note">
                        @if ($k['dir'] !== 'flat' && $k['good'] !== null)
                            @php $isGood = $k['dir'] === $k['good']; @endphp
                            <span class="gdb-trend {{ $isGood ? 'is-good' : 'is-bad' }}">@svg($k['dir'] === 'up' ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-right', 'gdb-trend-icon')<span class="sr-only">{{ $isGood ? 'Better' : 'Worse' }}:</span></span>
                        @endif
                        {{ $k['note'] }}
                    </p>
                </div>
            </article>
        @endforeach
    </section>
    @endif

    <section class="gdb-row">
        <article class="gdb-card gdb-summary">
            <h3 class="gdb-card-title">What's going on</h3>
            @if (! empty($d['briefing']))
                <p class="gdb-briefing"><strong>Morning briefing:</strong> {{ $d['briefing'] }}</p>
            @endif
            @forelse ($d['summary'] as $line)
                <p>{{ $line }}</p>
            @empty
                <p class="gdb-muted">Nothing to report yet.</p>
            @endforelse
        </article>
        <article class="gdb-card">
            <h3 class="gdb-card-title">Needs attention</h3>
            @if (count($d['alerts']))
                <ul class="gdb-alerts">
                    @foreach ($d['alerts'] as $a)
                        <li class="gdb-alert is-{{ $a['level'] }}">
                            @svg($statusIcon[$a['level']] ?? 'heroicon-s-information-circle', 'gdb-alert-icon')
                            <span class="sr-only">{{ ucfirst($a['level']) }}:</span>
                            @if ($a['url'])<a href="{{ $a['url'] }}">{{ $a['text'] }}</a>@else<span>{{ $a['text'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="gdb-ok">@svg('heroicon-s-check-circle', 'gdb-alert-icon') All clear. Nothing needs attention right now.</p>
            @endif
        </article>
    </section>

    <section class="gdb-charts">
        @isset($d['charts']['cashflow'])
            @php $c = $d['charts']['cashflow']; @endphp
            <article class="gdb-card gdb-wide">
                <div class="gdb-card-head">
                    <div>
                        <h3 class="gdb-card-title">Income and expenditure</h3>
                        <p class="gdb-muted">Last 12 months · received {{ $c['totals']['income'] }} · spent {{ $c['totals']['expenses'] }}</p>
                    </div>
                </div>
                <div class="gdb-canvas" style="height:320px"><canvas data-chart="cashflow" role="img" aria-label="Bar chart of monthly income and expenditure over the last 12 months, with net cash as a line"></canvas></div>
                <details class="gdb-table"><summary>Show figures</summary>
                    <table><thead><tr><th>Month</th><th>Income</th><th>Expenses</th><th>Net</th></tr></thead><tbody>
                    @foreach ($c['labels'] as $i => $l)<tr><td>{{ $l }}</td><td>₦{{ number_format($c['income'][$i]) }}</td><td>₦{{ number_format($c['expenses'][$i]) }}</td><td>₦{{ number_format($c['net'][$i]) }}</td></tr>@endforeach
                    </tbody></table></details>
            </article>
        @endisset

        @isset($d['charts']['requests'])
            @php $c = $d['charts']['requests']; @endphp
            <article class="gdb-card">
                <h3 class="gdb-card-title">Quote requests</h3>
                <p class="gdb-muted">Per week, by where they came from · {{ $c['total'] }} in 12 weeks</p>
                <div class="gdb-canvas" style="height:260px"><canvas data-chart="requests" role="img" aria-label="Stacked bar chart of quote requests per week by source"></canvas></div>
                <details class="gdb-table"><summary>Show figures</summary>
                    <table><thead><tr><th>Week of</th>@foreach (array_keys($c['series']) as $s)<th>{{ $s }}</th>@endforeach</tr></thead><tbody>
                    @foreach ($c['labels'] as $i => $l)<tr><td>{{ $l }}</td>@foreach ($c['series'] as $vals)<td>{{ $vals[$i] }}</td>@endforeach</tr>@endforeach
                    </tbody></table></details>
            </article>
        @endisset

        @isset($d['charts']['stages'])
            @php $c = $d['charts']['stages']; @endphp
            <article class="gdb-card">
                <h3 class="gdb-card-title">Active trips by stage</h3>
                <p class="gdb-muted">{{ array_sum($c['values']) }} shipments not yet delivered</p>
                <div class="gdb-canvas" style="height:260px"><canvas data-chart="stages" role="img" aria-label="Bar chart of active shipments at each stage"></canvas></div>
                <details class="gdb-table"><summary>Show figures</summary>
                    <table><tbody>@foreach ($c['labels'] as $i => $l)<tr><td>{{ $l }}</td><td>{{ $c['values'][$i] }}</td></tr>@endforeach</tbody></table></details>
            </article>
        @endisset

        @isset($d['charts']['expenses'])
            @php $c = $d['charts']['expenses']; @endphp
            <article class="gdb-card">
                <h3 class="gdb-card-title">Where the money goes</h3>
                <p class="gdb-muted">Expenses by category since {{ $c['since'] }}</p>
                <div class="gdb-canvas" style="height:260px"><canvas data-chart="expenses" role="img" aria-label="Bar chart of expenses by category this year"></canvas></div>
                <details class="gdb-table"><summary>Show figures</summary>
                    <table><tbody>@foreach ($c['labels'] as $i => $l)<tr><td>{{ $l }}</td><td>₦{{ number_format($c['values'][$i]) }}</td></tr>@endforeach</tbody></table></details>
            </article>
        @endisset
    </section>
</div>
<script type="application/json" id="gdb-data">@json($d['charts'])</script>
<script src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
<script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}"></script>
</x-filament-widgets::widget>
