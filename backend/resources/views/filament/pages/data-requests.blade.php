<x-filament-panels::page>
    <p class="gea-2fa-note">When someone asks for a copy of their data or asks us to delete it (Nigeria Data Protection Act 2023), check it is really them, then search by the email address or phone number they used with us. Answer within 30 days.</p>

    <form wire:submit="search" class="gea-ask">
        <input wire:model="subject" class="gea-ask-input" placeholder="Email address or phone number" aria-label="Email address or phone number">
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Find</x-filament::button>
    </form>
    @error('subject')<p class="gea-import-bad">{{ $message }}</p>@enderror

    @if ($counts !== null)
        @php($labels = ['contacts' => 'CRM contacts', 'customers' => 'Customer accounts', 'leads' => 'Enquiries', 'applications' => 'Job applications', 'chats' => 'Website chats', 'subscriptions' => 'Tracking email updates'])
        <x-filament::section heading="What we hold">
            @if (array_sum($counts) === 0)
                <p>Nothing found for this email or phone number.</p>
            @else
                <ul class="gea-import-help" style="list-style: disc">
                    @foreach ($counts as $k => $n)<li>{{ $labels[$k] ?? $k }}: <strong>{{ $n }}</strong></li>@endforeach
                </ul>
                <div class="gea-import-actions" style="margin-top: 12px">
                    <x-filament::button wire:click="export" color="gray" icon="heroicon-o-arrow-down-tray">Export a copy (zip)</x-filament::button>
                </div>
                <div class="gea-2fa-form" style="margin-top: 20px">
                    <label for="confirm"><strong>Erase:</strong> removes names, phone numbers, emails, notes, chats, job applications and email subscriptions. Customers with invoices keep their name, address and invoices, because tax law requires them. This cannot be undone. Type <strong>ERASE</strong> to confirm.</label>
                    <input id="confirm" wire:model="confirm" class="gea-ask-input" autocomplete="off">
                    @error('confirm')<p class="gea-import-bad">{{ $message }}</p>@enderror
                    <x-filament::button wire:click="erase" color="danger" icon="heroicon-o-trash">Erase this person's data</x-filament::button>
                </div>
            @endif
        </x-filament::section>
    @endif

    @if ($erased)
        <x-filament::section heading="Erased">
            <p>{{ collect($erased)->map(fn ($n, $k) => str_replace('_', ' ', $k).': '.$n)->implode(' · ') }}</p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Requests handled" collapsible>
        @php($rows = $this->history())
        @if ($rows->isEmpty())
            <p>None yet.</p>
        @else
            <div class="gea-ask-table"><table>
                <thead><tr><th>When</th><th>Type</th><th>Person</th><th>Handled by</th></tr></thead>
                <tbody>@foreach ($rows as $r)<tr><td>{{ $r->created_at->timezone('Africa/Lagos')->format('j M Y, g:ia') }}</td><td>{{ ucfirst($r->type) }}</td><td>{{ $r->subject_masked }}</td><td>{{ $r->handler?->name }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
