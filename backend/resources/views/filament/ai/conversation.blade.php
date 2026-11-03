<x-filament-panels::page>
    @php($c = $this->record->load('messages.user'))
    <div class="gea-chat" wire:poll.10s>
        <div class="gea-chat-meta">
            <span>Status: <strong>{{ \App\Models\Ai\Conversation::STATUSES[$c->status] ?? $c->status }}</strong></span>
            @if($c->visitor_phone)<span>Phone: <a href="tel:+{{ $c->visitor_phone }}">+{{ $c->visitor_phone }}</a></span>@endif
            @if($c->visitor_email)<span>Email: {{ $c->visitor_email }}</span>@endif
            @if($c->page)<span>Page: {{ $c->page }}</span>@endif
            @if($c->handoff_reason)<span>Why: {{ $c->handoff_reason }}</span>@endif
        </div>
        <ol class="gea-chat-list">
            @foreach($c->messages as $m)
                <li class="gea-chat-msg is-{{ $m->role }}">
                    <span class="gea-chat-who">
                        {{ match($m->role) { 'visitor' => $c->visitor_name ?? 'Visitor', 'assistant' => 'Assistant', 'staff' => $m->user?->name ?? 'Staff', default => 'System' } }}
                        · {{ $m->created_at->timezone('Africa/Lagos')->format('j M, g:ia') }}
                        @if($m->provider) · {{ $m->provider }} @endif
                    </span>
                    <p>{{ $m->body }}</p>
                </li>
            @endforeach
        </ol>
        @if($c->status !== 'closed')
            <form wire:submit="send" class="gea-chat-reply">
                <textarea wire:model="reply" rows="3" maxlength="2000" placeholder="Write a reply. Sending takes over the chat from the assistant." class="fi-input"></textarea>
                @error('reply')<p class="gea-chat-err">{{ $message }}</p>@enderror
                <x-filament::button type="submit" icon="heroicon-o-paper-airplane">Send reply</x-filament::button>
            </form>
        @endif
    </div>
</x-filament-panels::page>
