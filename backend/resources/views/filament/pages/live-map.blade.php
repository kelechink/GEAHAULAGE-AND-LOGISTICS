<x-filament-panels::page>
    @php $markers = $this->getMarkers(); @endphp
    <p class="gea-muted">{{ count($markers) }} active trucks with a position. Red markers have not updated in over an hour.</p>
    <div wire:ignore x-data x-init="
        const pts = {{ \Illuminate\Support\Js::from($markers) }};
        const load = (src, css) => new Promise(r => { if (window.L) return r(); const l = document.createElement('link'); l.rel='stylesheet'; l.href=css; document.head.appendChild(l); const s = document.createElement('script'); s.src = src; s.onload = r; document.head.appendChild(s); });
        load('{{ asset('vendor/leaflet/leaflet.js') }}', '{{ asset('vendor/leaflet/leaflet.css') }}').then(() => {
            const map = L.map($refs.map);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            const esc = s => String(s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
            pts.forEach(p => L.circleMarker([p.lat, p.lng], { radius: 9, weight: 3, color: '#0B1430', fillOpacity: 1, fillColor: p.stale ? '#C8102E' : '#E8A33D' })
                .addTo(map).bindPopup(`<b>${esc(p.code)}</b><br>${esc(p.route)}<br>${esc(p.truck)} · ${esc(p.stage)}<br>Updated ${esc(p.age)}<br><a href='${p.url}'>Open</a>`));
            pts.length ? map.fitBounds(pts.map(p => [p.lat, p.lng]), { padding: [30, 30], maxZoom: 9 }) : map.setView([9.08, 7.4], 6);
        });">
        <div x-ref="map" class="gea-map"></div>
    </div>
</x-filament-panels::page>
