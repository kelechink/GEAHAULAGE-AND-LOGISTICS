<x-filament-panels::page>
    <div class="gea-toolbar">
        <label>From <input type="date" wire:model.live="from" style="border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.3rem .5rem;background:transparent"></label>
        <label>To <input type="date" wire:model.live="to" style="border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.3rem .5rem;background:transparent"></label>
        <span class="gea-muted">{{ $this->periodLabel() }}</span>
    </div>
    <div class="gea-grid-2">
        @foreach ($this->getReports() as $key => $rep)
            <x-filament::section :heading="$rep['title']">
                <x-slot name="headerEnd">
                    <span style="display:flex;gap:.35rem">
                        @foreach (['csv' => 'CSV', 'pdf' => 'PDF', 'docx' => 'Word'] as $fmt => $label)
                            <x-filament::button size="xs" color="gray" wire:click="download('{{ $key }}', '{{ $fmt }}')">{{ $label }}</x-filament::button>
                        @endforeach
                    </span>
                </x-slot>
                <table class="gea-table">
                    <thead><tr>@foreach ($rep['headings'] as $i => $h)<th @class(['num' => $i > 0])>{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse (array_slice($rep['rows'], 0, 12) as $row)
                        <tr>@foreach ($row as $i => $v)<td @class(['num' => $i > 0])>{{ $v }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($rep['headings']) }}" class="gea-muted">No data in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if (count($rep['rows']) > 12)<p class="gea-muted" style="font-size:.75rem;margin-top:.5rem">Showing 12 of {{ count($rep['rows']) }}. Download for the full list.</p>@endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
