<x-filament-panels::page>
    <form wire:submit="ask" class="gea-ask">
        <input wire:model="question" class="gea-ask-input" maxlength="300" placeholder="e.g. Which trucks made the most money in October?" aria-label="Your question">
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled">Ask</x-filament::button>
    </form>
    <div class="gea-ask-examples">
        @foreach (['Which trucks made the most money last month?', 'List customers owing more than ₦500k', 'Which trips are delayed?', 'Which documents expire in the next 30 days?', 'Leads by source this month'] as $q)
            <button type="button" wire:click="example(@js($q))">{{ $q }}</button>
        @endforeach
    </div>
    <p class="gea-ask-note">Figures are worked out by the system from your records. The AI only reads your question to pick the report; your financial data is never sent to it.</p>

    @if ($answer)
        <x-filament::section :heading="$answer['title']">
            @if ($answer['note'])<p class="gea-ask-note">{{ $answer['note'] }}</p>@endif
            @if (count($answer['rows']))
                <div class="gea-ask-table">
                    <table>
                        <thead><tr>@foreach ($answer['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($answer['rows'] as $r)
                                <tr>@foreach ($r as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p>Nothing found.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
