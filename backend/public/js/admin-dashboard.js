/* Admin dashboard charts. Chart.js, validated palette per theme, hover tooltips, legends. */
(function () {
  'use strict';
  var el = document.getElementById('gdb-data');
  if (!el || !window.Chart) return;
  var data = JSON.parse(el.textContent);
  var root = document.documentElement;
  var charts = [];

  function css(name) { return getComputedStyle(document.querySelector('.gdb')).getPropertyValue(name).trim(); }
  function naira(v, compact) {
    if (!compact) return '₦' + Math.round(v).toLocaleString('en-NG');
    var a = Math.abs(v), s = v < 0 ? '-' : '';
    if (a >= 1e9) return s + '₦' + (a / 1e9).toFixed(1).replace(/\.0$/, '') + 'bn';
    if (a >= 1e6) return s + '₦' + (a / 1e6).toFixed(1).replace(/\.0$/, '') + 'm';
    if (a >= 1e3) return s + '₦' + Math.round(a / 1e3) + 'k';
    return s + '₦' + Math.round(a);
  }

  function base(kind) {
    var ink2 = css('--ink-2'), rule = css('--rule'), card = css('--card');
    return {
      responsive: true, maintainAspectRatio: false, animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 600 },
      interaction: { mode: kind === 'h' ? 'nearest' : 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', align: 'start', labels: { sort: function (a, b) { return a.datasetIndex - b.datasetIndex; }, color: ink2, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, padding: 14 } },
        tooltip: { backgroundColor: css('--ink'), titleColor: card, bodyColor: card, padding: 10, cornerRadius: 8, boxPadding: 4, usePointStyle: true },
      },
      scales: {
        x: { grid: { display: kind === 'h', color: rule, drawTicks: false }, border: { display: false }, ticks: { color: ink2, padding: 6 } },
        y: { grid: { display: kind !== 'h', color: rule, drawTicks: false }, border: { display: false }, ticks: { color: ink2, padding: 8 }, beginAtZero: true },
      },
    };
  }

  // 2px surface gap between adjacent fills; 4px rounded data end anchored to the baseline.
  function bar(color, extra) {
    return Object.assign({ backgroundColor: color, hoverBackgroundColor: color, borderColor: css('--card'), borderWidth: 2,
      borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 }, borderSkipped: 'bottom', maxBarThickness: 28 }, extra || {});
  }

  function build() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];

    var c = data.cashflow, cv = document.querySelector('[data-chart="cashflow"]');
    if (c && cv) {
      var o = base();
      o.scales.y.ticks.callback = function (v) { return naira(v, true); };
      o.plugins.tooltip.callbacks = { label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + naira(ctx.parsed.y); } };
      charts.push(new Chart(cv, { data: { labels: c.labels, datasets: [
        Object.assign(bar(css('--s1')), { type: 'bar', label: 'Income', data: c.income, order: 2 }),
        Object.assign(bar(css('--s2')), { type: 'bar', label: 'Expenses', data: c.expenses, order: 2 }),
        { type: 'line', label: 'Net cash', data: c.net, borderColor: css('--s3'), backgroundColor: css('--s3'), borderWidth: 2, tension: 0,
          pointRadius: 4, pointHoverRadius: 6, pointBorderColor: css('--card'), pointBorderWidth: 2, order: 1 },
      ] }, options: o }));
    }

    var r = data.requests, rv = document.querySelector('[data-chart="requests"]');
    if (r && rv) {
      var ro = base();
      ro.scales.x.stacked = true; ro.scales.y.stacked = true; ro.scales.y.ticks.precision = 0;
      var slots = ['--s1', '--s2', '--s3', '--s4'];
      var ds = Object.keys(r.series).map(function (name, i) {
        var d = bar(css(slots[i]), { label: name, data: r.series[name], stack: 'req' });
        if (i < Object.keys(r.series).length - 1) d.borderRadius = 0; // only the top segment is rounded
        return d;
      });
      charts.push(new Chart(rv, { type: 'bar', data: { labels: r.labels, datasets: ds }, options: ro }));
    }

    function hbar(key, color, money) {
      var d = data[key], v = document.querySelector('[data-chart="' + key + '"]');
      if (!d || !v) return;
      var o = base('h');
      o.indexAxis = 'y';
      o.plugins.legend.display = false; // single series: the title names it
      o.scales.x.ticks.callback = function (val) { return money ? naira(val, true) : val; };
      if (!money) o.scales.x.ticks.precision = 0;
      o.plugins.tooltip.callbacks = { label: function (ctx) { return ' ' + (money ? naira(ctx.parsed.x) : ctx.parsed.x + ' shipments'); } };
      charts.push(new Chart(v, { type: 'bar', data: { labels: d.labels, datasets: [bar(css(color), {
        label: key, data: d.values, borderRadius: { topRight: 4, bottomRight: 4, topLeft: 0, bottomLeft: 0 }, borderSkipped: 'left', maxBarThickness: 22,
      })] }, options: o }));
    }
    hbar('stages', '--s7', false);
    hbar('expenses', '--s2', true);
  }

  build();
  // Filament toggles the "dark" class on <html>; rebuild with that mode's palette.
  new MutationObserver(function (m) { if (m.some(function (x) { return x.attributeName === 'class'; })) build(); })
    .observe(root, { attributes: true, attributeFilter: ['class'] });
})();
