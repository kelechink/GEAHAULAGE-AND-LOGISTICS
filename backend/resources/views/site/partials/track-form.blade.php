<form method="get" action="{{ route('track') }}" role="search" aria-label="Track a shipment">
    <label class="sr-only" for="track-code">Tracking code</label>
    <div class="track-field">
        <input id="track-code" name="code" value="{{ $code ?? '' }}" placeholder="Enter tracking code, e.g. GEA-2611-K7QXM" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
        <button class="btn btn-signal" type="submit">Track</button>
    </div>
</form>
