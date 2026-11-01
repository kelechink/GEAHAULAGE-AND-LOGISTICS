/* Live tracking page: Leaflet map + 15-second polling (PRD 8.5). Plain JS. */
(function () {
  'use strict';
  var root = document.querySelector('[data-tracking]');
  var dataEl = document.getElementById('trk-data');
  if (!root || !dataEl || !window.L) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var data = JSON.parse(dataEl.textContent);
  var GEA_BLUE = '#1E3A8A', SIGNAL = '#E8A33D';

  var map = L.map(root.querySelector('[data-map-el]'), { scrollWheelZoom: false, attributionControl: true });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  var planned = data.planned.map(function (p) { return [p.lat, p.lng]; });
  if (planned.length) {
    L.polyline(planned, { color: '#8C93A6', weight: 3, dashArray: '6 8', opacity: .9 }).addTo(map);
    L.circleMarker(planned[0], { radius: 6, color: GEA_BLUE, fillColor: '#fff', fillOpacity: 1, weight: 3 }).addTo(map).bindTooltip(data.origin);
    L.circleMarker(planned[planned.length - 1], { radius: 6, color: GEA_BLUE, fillColor: GEA_BLUE, fillOpacity: 1 }).addTo(map).bindTooltip(data.destination);
  }

  var travelled = L.polyline([], { color: GEA_BLUE, weight: 4 }).addTo(map);
  var truckIcon = L.divIcon({ className: '', html: '<div class="trk-truck" aria-hidden="true"></div>', iconSize: [22, 22], iconAnchor: [11, 11] });
  var truck = null;

  function drawPath(points, animate) {
    if (!points.length) return;
    if (!animate || reduce) { points.forEach(function (p) { travelled.addLatLng([p.lat, p.lng]); }); return; }
    var i = 0;
    (function step() { if (i < points.length) { travelled.addLatLng([points[i].lat, points[i].lng]); i++; requestAnimationFrame(step); } })();
  }

  function moveTruck(last) {
    if (!last) return;
    var to = L.latLng(last.lat, last.lng);
    if (!truck) { truck = L.marker(to, { icon: truckIcon, keyboard: false }).addTo(map); }
    else if (reduce) { truck.setLatLng(to); }
    else {
      var from = truck.getLatLng(), t0 = performance.now(), dur = 1500;
      (function glide(t) {
        var k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
        truck.setLatLng([from.lat + (to.lat - from.lat) * e, from.lng + (to.lng - from.lng) * e]);
        if (k < 1) requestAnimationFrame(glide);
      })(t0);
    }
    if (last.place) truck.bindTooltip(last.place);
  }

  drawPath(data.path, true);
  moveTruck(data.last);
  var bounds = planned.concat(data.path.map(function (p) { return [p.lat, p.lng]; }));
  if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] }); else map.setView([9.08, 7.4], 6);

  var since = data.server_time;
  var timeline = root.querySelector('[data-timeline]');
  function render(d) {
    root.querySelector('[data-stage]').textContent = d.stage_label;
    root.querySelector('[data-updated]').textContent = d.updated_ago || 'Waiting for the first update';
    root.querySelector('[data-eta]').textContent = d.eta ? ' · ' + d.eta : '';
    var delay = root.querySelector('[data-delay]');
    delay.hidden = !d.delay_reason; delay.querySelector('span').textContent = d.delay_reason || '';
    var bar = root.querySelector('[data-progressbar]');
    bar.setAttribute('aria-valuenow', d.progress); bar.firstElementChild.style.width = d.progress + '%';
    d.events.forEach(function (e) {
      if (timeline.querySelector('[data-event="' + e.id + '"]')) return;
      var li = document.createElement('li'); li.setAttribute('data-event', e.id); li.className = 'is-new';
      var b = document.createElement('b'); b.textContent = e.label; li.appendChild(b);
      if (e.place) { var s = document.createElement('span'); s.textContent = e.place; li.appendChild(s); }
      var tm = document.createElement('time'); tm.dateTime = e.at; tm.textContent = e.at_label; li.appendChild(tm);
      if (e.note) { var p = document.createElement('p'); p.textContent = e.note; li.appendChild(p); }
      timeline.insertBefore(li, timeline.firstChild);
    });
    drawPath(d.path, true);
    moveTruck(d.last);
  }

  var url = root.getAttribute('data-updates'), timer = null;
  function poll() {
    if (document.hidden) return;
    fetch(url + '?since=' + encodeURIComponent(since), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) { since = d.server_time; render(d); } })
      .catch(function () {});
  }
  timer = setInterval(poll, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  if (['delivered', 'closed', 'cancelled'].indexOf(data.stage) !== -1) clearInterval(timer);
})();
