<x-filament-panels::page>
    <form class="gea-import">
        {{ $this->form }}
        <div class="gea-import-actions">
            <x-filament::button type="button" wire:click="check" color="gray" icon="heroicon-o-magnifying-glass">Check file</x-filament::button>
            <x-filament::button type="button" wire:click="save" icon="heroicon-o-arrow-up-tray"
                :disabled="! $report || count($report['errors']) || $report['committed']">Import</x-filament::button>
        </div>
    </form>

    <x-filament::section heading="How it works" collapsible collapsed>
        <ol class="gea-import-help">
            <li>Download the template for what you are importing and fill one row per record. Excel (.xlsx) and CSV both work.</li>
            <li>Import trucks before drivers, so each driver's usual truck can be linked.</li>
            <li>Click <strong>Check file</strong>. Nothing is saved yet. Fix any rows listed and upload again.</li>
            <li>When the check is clean, click <strong>Import</strong>. Existing records are updated (customers by name, email or phone; trucks by registration; drivers by phone; towns by name and state), so you can import the same file again safely.</li>
        </ol>
    </x-filament::section>

    @if ($report)
        <x-filament::section :heading="$report['committed'] ? 'Imported' : 'Check result'">
            <p>{{ $report['rows'] }} rows: <strong>{{ $report['created'] }}</strong> new, <strong>{{ $report['updated'] }}</strong> {{ $report['committed'] ? 'updated' : 'to update' }}.</p>
            @if (count($report['errors']))
                <p class="gea-import-bad">{{ count($report['errors']) }} rows need fixing. Nothing has been saved.</p>
                <ul class="gea-import-errors">
                    @foreach (array_slice($report['errors'], 0, 50) as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            @elseif (! $report['committed'])
                <p class="gea-import-good">The file looks good. Click Import to save.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
