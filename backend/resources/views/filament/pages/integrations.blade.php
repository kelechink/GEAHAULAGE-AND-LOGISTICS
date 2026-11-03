<x-filament-panels::page>
    <form wire:submit="save" class="fi-form" style="display:flex;flex-direction:column;gap:1.5rem">
        {{ $this->form }}
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <x-filament::button type="submit">Save</x-filament::button>
            {{ $this->rotateSecretAction }}
        </div>
    </form>

    @if ($newSecret)
        <x-filament::section heading="New webhook secret" icon="heroicon-o-key">
            <p class="gea-muted" style="margin-bottom:.5rem">Copy it now and send it to the tracker company securely. It won't be shown again.</p>
            <code style="display:block;padding:.75rem;border-radius:.5rem;background:rgba(23,47,102,.08);word-break:break-all;user-select:all">{{ $newSecret }}</code>
        </x-filament::section>
    @endif
    <x-filament-actions::modals />
</x-filament-panels::page>
