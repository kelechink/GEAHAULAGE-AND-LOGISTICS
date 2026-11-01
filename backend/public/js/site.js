/* GEA public site. Plain JS, no framework. Honours prefers-reduced-motion and data saver. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var saveData = !!(navigator.connection && navigator.connection.saveData);
  var $ = function (s, el) { return (el || doc).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || doc).querySelectorAll(s)); };

  /* Header: solid after scrolling, plus a reading-progress line. */
  var nav = $('[data-nav]'), prog = $('[data-progress]');
  function onScroll() {
    var y = window.scrollY, max = doc.documentElement.scrollHeight - window.innerHeight;
    if (nav) nav.classList.toggle('is-solid', y > 60);
    if (prog) prog.style.width = (max > 0 ? (y / max) * 100 : 0) + '%';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Theme: light by default, dark on request, remembered per browser. */
  var themeLabels = $$('[data-theme-label]'), map = $('[data-map]');
  function applyTheme(t) {
    root.setAttribute('data-theme', t);
    themeLabels.forEach(function (l) { l.textContent = t === 'dark' ? 'Light' : 'Dark'; });
    if (map && map.contentWindow) map.contentWindow.postMessage({ geaTheme: t }, '*');
  }
  applyTheme(root.getAttribute('data-theme') || 'light');
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem('gea-theme', t); } catch (e) {}
      applyTheme(t);
    });
  });
  if (map) map.addEventListener('load', function () { applyTheme(root.getAttribute('data-theme') || 'light'); });

  /* Mobile menu: a modal dialog, closed by the X, Escape or choosing a link. */
  var menu = $('#mobile-menu'), opener = $('[data-menu-open]');
  function setMenu(open) {
    if (!menu) return;
    menu.hidden = !open;
    if (opener) opener.setAttribute('aria-expanded', String(open));
    doc.body.style.overflow = open ? 'hidden' : '';
    if (open) { var c = $('[data-menu-close]', menu); if (c) c.focus(); } else if (opener) opener.focus();
  }
  if (opener) opener.addEventListener('click', function () { setMenu(true); });
  if (menu) {
    $$('[data-menu-close], nav a', menu).forEach(function (el) { el.addEventListener('click', function () { setMenu(false); }); });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) setMenu(false); });
  }

  /* Hero: headline lines rise in one after another, then the intro fades up. */
  if (!reduce && 'animate' in Element.prototype) {
    var ease = 'cubic-bezier(.2,.7,.2,1)';
    $$('[data-hero-line]').forEach(function (el, i) {
      el.animate([{ transform: 'translateY(110%)' }, { transform: 'none' }], { duration: 800, delay: i * 40, easing: ease, fill: 'both' });
    });
    $$('[data-hero-fade]').forEach(function (el) {
      el.animate([{ opacity: 0, transform: 'translateY(24px)' }, { opacity: 1, transform: 'none' }], { duration: 700, delay: 450, easing: ease, fill: 'both' });
    });
  }

  /* Hero videos play in turn, with progress segments and a pause control. */
  var heroVids = $$('[data-hero-vid]'), segs = $$('[data-seg]'), toggle = $('[data-video-toggle]');
  var cur = 0, paused = reduce || saveData;
  function setToggle() {
    if (!toggle) return;
    toggle.textContent = paused ? '▶' : '❚❚';
    toggle.setAttribute('aria-label', paused ? 'Play background video' : 'Pause background video');
  }
  function showHero(i) {
    cur = i;
    heroVids.forEach(function (v, j) {
      v.style.opacity = j === i ? '1' : '0';
      if (j === i) {
        if (v.dataset.poster) { v.poster = v.dataset.poster; delete v.dataset.poster; }
        if (j !== 0 || v.currentTime > 0) v.currentTime = 0;
        if (!paused) { v.preload = 'auto'; v.play().catch(function () {}); }
      }
      else window.setTimeout(function () { if (cur !== j) v.pause(); }, 1300);
    });
    segs.forEach(function (s, j) { s.style.width = j < i ? '100%' : '0%'; });
  }
  if (heroVids.length) {
    heroVids.forEach(function (v, j) {
      v.addEventListener('ended', function () { if (j === cur) showHero((cur + 1) % heroVids.length); });
      v.addEventListener('timeupdate', function () {
        if (j !== cur || !v.duration) return;
        if (segs[j]) segs[j].style.width = (v.currentTime / v.duration * 100) + '%';
        var next = heroVids[(j + 1) % heroVids.length];
        if (v.duration - v.currentTime < 3 && next.dataset.poster) { next.poster = next.dataset.poster; delete next.dataset.poster; }
      });
    });
    setToggle();
    // Start the hero video only after the page has loaded, so the poster and text paint first.
    var startHero = function () { if (!paused) { heroVids[0].preload = 'auto'; heroVids[0].play().catch(function () {}); } };
    if (doc.readyState === 'complete') startHero(); else window.addEventListener('load', startHero);
    if (toggle) toggle.addEventListener('click', function () {
      paused = !paused;
      var v = heroVids[cur];
      if (paused) v.pause(); else { v.preload = 'auto'; v.play().catch(function () {}); }
      setToggle();
    });
  }

  /* Below-the-fold media: fetch posters and images only when the section comes near the screen. */
  var near = $$('video[data-poster][data-lazy], .svc-stage');
  if ('IntersectionObserver' in window) {
    var nio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target; nio.unobserve(el);
        if (el.tagName === 'VIDEO') { el.poster = el.dataset.poster; delete el.dataset.poster; }
        else { el.classList.add('is-near'); $$('video[data-poster]', el).forEach(function (v) { v.poster = v.dataset.poster; delete v.dataset.poster; }); }
      });
    }, { rootMargin: '600px 0px' });
    near.forEach(function (el) { nio.observe(el); });
  } else {
    near.forEach(function (el) { if (el.tagName === 'VIDEO') el.poster = el.dataset.poster; else el.classList.add('is-near'); });
  }

  /* Other videos load and play only while on screen. */
  var lazyVids = $$('video[data-lazy]');
  if (lazyVids.length && !reduce && !saveData && 'IntersectionObserver' in window) {
    var vio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        var v = en.target;
        if (en.isIntersecting) { v.preload = 'auto'; v.play().catch(function () {}); } else v.pause();
      });
    }, { threshold: 0.15 });
    lazyVids.forEach(function (v) { vio.observe(v); });
  }

  /* Reveal once when 15% of an element is in view. Counters count up the same way. */
  if ('IntersectionObserver' in window && !reduce) {
    var rio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        rio.unobserve(el);
        if (el.hasAttribute('data-reveal')) el.classList.add('is-in');
        if (el.hasAttribute('data-count')) countUp(el);
      });
    }, { threshold: 0.15 });
    $$('[data-reveal], [data-count]').forEach(function (el) { rio.observe(el); });
  } else {
    $$('[data-reveal]').forEach(function (el) { el.classList.add('is-in'); });
  }
  function countUp(el) {
    var end = parseInt(el.getAttribute('data-count'), 10), start = end > 1000 ? end - 20 : 0, t0 = performance.now(), dur = 1200;
    (function tick(t) {
      var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(start + (end - start) * e));
      if (p < 1) requestAnimationFrame(tick);
    })(t0);
  }

  /* Services: hovering or focusing a service shows its picture and link. */
  var items = $$('[data-svc]'), slides = $$('[data-slide]'), title = $('[data-svc-title]'), link = $('[data-svc-link]');
  function activate(i) {
    items.forEach(function (el, j) { el.classList.toggle('is-on', j === i); });
    slides.forEach(function (el, j) {
      el.classList.toggle('is-on', j === i);
      var v = $('video', el);
      if (v && !reduce && !saveData) { if (j === i) { v.preload = 'auto'; v.play().catch(function () {}); } else v.pause(); }
    });
    if (title) title.textContent = $('.t', items[i]).textContent;
    if (link) link.href = items[i].href;
  }
  items.forEach(function (el, i) {
    el.addEventListener('mouseenter', function () { activate(i); });
    el.addEventListener('focus', function () { activate(i); });
  });
})();
