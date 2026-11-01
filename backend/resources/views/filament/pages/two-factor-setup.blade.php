<x-filament-panels::page>
    @if (count($recoveryCodes))
        <x-filament::section heading="Save your recovery codes">
            <p class="gea-2fa-note">If you lose your phone, each of these codes lets you sign in once. Print them or keep them in a password manager. They will not be shown again.</p>
            <ul class="gea-2fa-codes">@foreach ($recoveryCodes as $c)<li>{{ $c }}</li>@endforeach</ul>
            <x-filament::button tag="a" :href="filament()->getUrl()" icon="heroicon-o-check">I have saved them, continue</x-filament::button>
        </x-filament::section>
    @elseif ($this->enabled())
        <x-filament::section heading="Two-factor sign-in is on">
            <p class="gea-2fa-note">You enter a code from your authenticator app each time you sign in. Lost your phone? Use a recovery code, or ask the administrator to reset two-factor for you.</p>
            <x-filament::button wire:click="newRecoveryCodes" color="gray" icon="heroicon-o-arrow-path">Make new recovery codes</x-filament::button>
        </x-filament::section>
    @else
        <x-filament::section heading="Protect your account">
            <ol class="gea-2fa-steps">
                <li>Install an authenticator app on your phone: Google Authenticator, Microsoft Authenticator or similar (free).</li>
                <li>In the app, add an account and scan this code.</li>
            </ol>
            <img class="gea-2fa-qr" src="{{ $this->qr() }}" alt="QR code to add GEA to your authenticator app" width="200" height="200">
            <p class="gea-2fa-note">Can't scan? Enter this key in the app: <code class="gea-2fa-key">{{ trim(chunk_split($this->secret(), 4, ' ')) }}</code></p>
            <form wire:submit="confirm" class="gea-2fa-form">
                <label for="code">3. Type the 6-digit code the app shows</label>
                <input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="gea-ask-input" autofocus>
                @error('code')<p class="gea-import-bad">{{ $message }}</p>@enderror
                <x-filament::button type="submit" icon="heroicon-o-shield-check">Turn on two-factor sign-in</x-filament::button>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
