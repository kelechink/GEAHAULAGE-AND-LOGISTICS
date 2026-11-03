<x-filament-panels::page>
    @php $r = $this->getReport(); @endphp
    <div class="gea-toolbar">
        <label for="period" class="text-sm font-medium">Period</label>
        <select id="period" wire:model.live="period" class="fi-select-input" style="border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.35rem .6rem;background:transparent">
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last 12 months</option>
        </select>
        <span class="gea-muted">Since {{ $r['from'] }}</span>
    </div>

    <div class="gea-grid-2">
        <x-filament::section heading="Response time">
            @if ($r['response']['contacted'] === 0)
                <p class="gea-muted">No leads contacted in this period yet.</p>
            @else
                <dl style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="gea-stat"><dt>Median time to first contact</dt><dd>{{ $r['response']['median_hours'] }} h</dd></div>
                    <div class="gea-stat"><dt>Contacted within 1 working day</dt><dd>{{ $r['response']['within_target_percent'] }}%</dd><dd class="gea-muted" style="font-size:.75rem;font-weight:400">Target 90%</dd></div>
                </dl>
            @endif
        </x-filament::section>

        <x-filament::section heading="Leads by source">
            <table class="gea-table">
                <thead><tr><th>Source</th><th class="num">Leads</th><th class="num">Won</th></tr></thead>
                <tbody>
                @forelse ($r['bySource'] as $row)
                    <tr><td>{{ $row['source'] }}</td><td class="num">{{ $row['leads'] }}</td><td class="num">{{ $row['won'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="gea-muted">No leads in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Conversion by stage">
            <table class="gea-table">
                <thead><tr><th>Stage reached</th><th class="num">Leads</th><th class="num">Share</th></tr></thead>
                <tbody>
                @foreach ($r['funnel'] as $row)
                    <tr><td>{{ $row['stage'] }}</td><td class="num">{{ $row['reached'] }}</td><td class="num">{{ $row['percent'] }}%</td></tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Won value by month">
            <table class="gea-table">
                <thead><tr><th>Month</th><th class="num">Won</th><th class="num">Estimated value</th></tr></thead>
                <tbody>
                @foreach ($r['wonByMonth'] as $row)
                    <tr><td>{{ $row['month'] }}</td><td class="num">{{ $row['won'] }}</td><td class="num">{{ $row['value'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
