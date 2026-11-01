<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="verify" class="gea-2fa-form">
            <label for="code">Open your authenticator app and type the 6-digit code for GEA Haulage. Lost your phone? Type one of your recovery codes.</label>
            <input id="code" wire:model="code" autocomplete="one-time-code" maxlength="20" class="gea-ask-input" autofocus>
            @error('code')<p class="gea-import-bad">{{ $message }}</p>@enderror
            <x-filament::button type="submit" icon="heroicon-o-lock-open">Continue</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
