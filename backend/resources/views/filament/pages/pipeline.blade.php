<x-filament-panels::page>
    <div class="gea-toolbar">
        <label style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" wire:model.live="mineOnly"> Only my leads
        </label>
        @if ($this->canManage())
            <span class="gea-muted">Drag a card to another column, or use its "Move to" menu.</span>
        @endif
    </div>

    <div class="gea-board" x-data="{ dragging: null, over: null }">
        @foreach ($this->getColumns() as $stage => $col)
            <section class="gea-col" :class="over === '{{ $stage }}' && 'is-over'"
                     @dragover.prevent="over = '{{ $stage }}'" @dragleave="over = null"
                     @drop.prevent="over = null; if (dragging) { $wire.moveLead(dragging, '{{ $stage }}'); dragging = null }"
                     aria-label="{{ $col['label'] }} column">
                <header>
                    <h2>{{ $col['label'] }} <span>({{ $col['leads']->count() }})</span></h2>
                    <span class="gea-total">{{ $col['total'] }}</span>
                </header>
                <div class="gea-cards">
                    @forelse ($col['leads'] as $lead)
                        <article class="gea-card" wire:key="lead-{{ $lead->id }}" data-lead="{{ $lead->id }}"
                                 @if ($this->canManage()) draggable="true" @dragstart="dragging = {{ $lead->id }}" @dragend="over = null" @endif>
                            <a class="name" href="{{ \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $lead]) }}">{{ $lead->contact->name }}</a>
                            <p>{{ $lead->origin ?? '?' }} → {{ $lead->destination ?? '?' }}</p>
                            @if ($lead->service_interest)<p>{{ $lead->service_interest }}</p>@endif
                            <div class="meta">
                                <span>{{ $lead->owner?->name ?? 'Unassigned' }}</span>
                                @if ($lead->next_follow_up_at && $lead->isOpen())
                                    <span @class(['late' => $lead->next_follow_up_at->isPast()])>{{ $lead->next_follow_up_at->timezone('Africa/Lagos')->format('j M, g:ia') }}</span>
                                @endif
                            </div>
                            @if ($this->canManage())
                                <label class="sr-only" for="move-{{ $lead->id }}">Move {{ $lead->contact->name }} to</label>
                                <select id="move-{{ $lead->id }}" x-on:change="$wire.moveLead({{ $lead->id }}, $event.target.value); $event.target.value = ''">
                                    <option value="">Move to…</option>
                                    @foreach (\App\Enums\LeadStage::cases() as $to)
                                        @continue($to->value === $stage)
                                        <option value="{{ $to->value }}">{{ $to->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </article>
                    @empty
                        <p class="gea-muted" style="font-size:.75rem">No leads.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
