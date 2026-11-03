@php
    /** @var \App\Models\Shipment $record */
    $record = $getRecord();
    $payload = app(\App\Domain\Tracking\PublicTracking::class)->snapshot($record);
@endphp
<div wire:ignore x-data x-init="
    const d = {{ \Illuminate\Support\Js::from($payload) }};
    const load = (src, css) => new Promise(r => { if (window.L) return r(); if (css) { const l = document.createElement('link'); l.rel='stylesheet'; l.href=css; document.head.appendChild(l); } const s = document.createElement('script'); s.src = src; s.onload = r; document.head.appendChild(s); });
    load('{{ asset('vendor/leaflet/leaflet.js') }}', '{{ asset('vendor/leaflet/leaflet.css') }}').then(() => {
        const map = L.map($refs.map, { scrollWheelZoom: false });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        const planned = d.planned.map(p => [p.lat, p.lng]);
        if (planned.length) L.polyline(planned, { color: '#8C93A6', dashArray: '6 8', weight: 3 }).addTo(map);
        const path = d.path.map(p => [p.lat, p.lng]);
        if (path.length) L.polyline(path, { color: '#1E3A8A', weight: 4 }).addTo(map);
        if (d.last) L.circleMarker([d.last.lat, d.last.lng], { radius: 9, color: '#0B1430', fillColor: '#E8A33D', fillOpacity: 1, weight: 3 }).addTo(map).bindTooltip(d.last.place || 'Last position');
        const all = planned.concat(path);
        all.length ? map.fitBounds(all, { padding: [24, 24] }) : map.setView([9.08, 7.4], 6);
    });">
    <div x-ref="map" class="gea-map" style="height:380px"></div>
    <p class="gea-muted" style="margin-top:.5rem;font-size:.8rem">Grey dashes: planned route. Blue: path travelled. Gold dot: last known position{{ $payload['eta'] ? '. '.$payload['eta'] : '' }}.</p>
</div>
