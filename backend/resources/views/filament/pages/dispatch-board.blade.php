<x-filament-panels::page>
    <div class="gea-board" wire:poll.60s>
        @foreach ($this->getColumns() as $stage => $col)
            <section class="gea-col" aria-label="{{ $col['label'] }}">
                <header><h2>{{ $col['label'] }} <span>({{ $col['items']->count() }})</span></h2></header>
                <div class="gea-cards">
                    @forelse ($col['items'] as $s)
                        <article class="gea-card" style="cursor:default">
                            <a class="name" href="{{ \App\Filament\Resources\ShipmentResource::getUrl('view', ['record' => $s]) }}">{{ $s->tracking_code }}</a>
                            <p>{{ $s->origin }} → {{ $s->destination }}</p>
                            <p>{{ $s->customer->name }}@if ($s->tonnage) · {{ rtrim(rtrim($s->tonnage, '0'), '.') }} t @endif</p>
                            <div class="meta">
                                <span>{{ $s->truck?->registration ?? 'No truck' }}</span>
                                @if ($s->stage === \App\Enums\ShipmentStage::Delayed)
                                    <span class="gea-pill warn">Delayed</span>
                                @elseif ($s->isStale())
                                    <span class="gea-pill warn">Stale</span>
                                @elseif ($s->last_update_at)
                                    <span>{{ $s->last_update_at->diffForHumans(short: true) }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="gea-muted" style="font-size:.75rem">None.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
