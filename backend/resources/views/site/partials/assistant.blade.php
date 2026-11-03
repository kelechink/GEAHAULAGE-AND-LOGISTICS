{{-- Website assistant (PRD 12.3). Closed by default; prompts appear as a small teaser, never an auto-opened window. --}}
<div class="gea-ai" id="gea-ai" data-api="{{ url('/api/assistant') }}" data-phone="{{ $company->phone }}">
    <div class="ai-teaser" data-ai-teaser hidden>
        <button type="button" class="ai-teaser-text" data-ai-teaser-open><span data-ai-teaser-text></span></button>
        <div class="ai-teaser-actions">
            <button type="button" data-ai-never>Don't show again</button>
            <button type="button" data-ai-dismiss aria-label="Dismiss">×</button>
        </div>
    </div>

    <section class="ai-panel" data-ai-panel hidden role="dialog" aria-label="Chat with GEA">
        <header class="ai-head">
            <div><strong>GEA assistant</strong><span>Quotes, tracking and questions</span></div>
            <button type="button" data-ai-close aria-label="Close chat">×</button>
        </header>
        <p class="ai-notice">Chats are saved to help our team respond. Please don't share bank or card details.</p>
        <div class="ai-list" data-ai-list aria-live="polite"></div>
        <div class="ai-quick" data-ai-quick>
            <button type="button" data-say="I'd like a quote">Get a quote</button>
            <button type="button" data-say="I want to track a shipment">Track a shipment</button>
            <button type="button" data-say="Please call me back">Call me back</button>
        </div>
        <form class="ai-form" data-ai-form autocomplete="off">
            <label class="sr-only" for="ai-input">Your message</label>
            <input id="ai-input" data-ai-input maxlength="1000" placeholder="Type your message…" required>
            <button type="submit" class="ai-send" aria-label="Send">Send</button>
        </form>
    </section>

    <button type="button" class="ai-bubble" data-ai-bubble aria-expanded="false" aria-controls="gea-ai">
        <span class="ai-dot" aria-hidden="true"></span>Ask GEA
    </button>
</div>
