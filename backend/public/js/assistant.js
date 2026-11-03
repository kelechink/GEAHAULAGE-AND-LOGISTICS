/* GEA website assistant (PRD 12.3). Talks only to /api/assistant on our server. No data is stored until the visitor chats. */
(function () {
  'use strict';
  var doc = document, root = doc.getElementById('gea-ai');
  if (!root || !window.fetch) return;
  var api = root.getAttribute('data-api'), phone = root.getAttribute('data-phone');
  var $ = function (s) { return root.querySelector(s); };
  var bubble = $('[data-ai-bubble]'), teaser = $('[data-ai-teaser]'), panel = $('[data-ai-panel]');
  var list = $('[data-ai-list]'), form = $('[data-ai-form]'), input = $('[data-ai-input]'), quick = $('[data-ai-quick]');
  var ss = window.sessionStorage, token = null, lastId = 0, status = 'bot', pollTimer = null, busy = false;
  try { token = ss.getItem('gea-ai-token'); lastId = +(ss.getItem('gea-ai-last') || 0); } catch (e) {}
  var off = /(?:^|; )gea_ai_off=1/.test(doc.cookie);

  function el(tag, cls, text) { var n = doc.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function save() { try { if (token) ss.setItem('gea-ai-token', token); ss.setItem('gea-ai-last', String(lastId)); } catch (e) {} }

  /* Messages are rendered as text; links are made clickable without using innerHTML. */
  function addText(node, text) {
    String(text).split(/(https?:\/\/[^\s)]+)/).forEach(function (part, i) {
      if (i % 2) { var a = el('a', null, part); a.href = part; a.rel = 'noopener'; node.appendChild(a); }
      else if (part) node.appendChild(doc.createTextNode(part));
    });
  }
  function render(m) {
    if (m.id && m.id <= lastId) return;
    if (m.id) lastId = m.id;
    var row = el('div', 'ai-msg ai-' + m.role);
    if (m.name) row.appendChild(el('span', 'ai-who', m.name));
    var p = el('p'); addText(p, m.body); row.appendChild(p);
    list.appendChild(row);
    if (m.meta && m.meta.form === 'lead') list.appendChild(leadForm());
    list.scrollTop = list.scrollHeight;
  }
  function system(text) { list.appendChild(el('div', 'ai-msg ai-system', text)); list.scrollTop = list.scrollHeight; }

  function leadForm() {
    var f = el('form', 'ai-lead');
    [['name', 'Your name', 'text', true], ['phone', 'Phone number', 'tel', true], ['email', 'Email (optional)', 'email', false], ['details', 'What do you need moved? (optional)', 'text', false]]
      .forEach(function (d) { var i = el('input'); i.name = d[0]; i.placeholder = d[1]; i.type = d[2]; i.required = d[3]; i.setAttribute('aria-label', d[1]); f.appendChild(i); });
    var b = el('button', 'ai-send', 'Send my details'); b.type = 'submit'; f.appendChild(b);
    f.addEventListener('submit', function (e) {
      e.preventDefault(); b.disabled = true;
      var body = { token: token }; Array.prototype.forEach.call(f.elements, function (x) { if (x.name) body[x.name] = x.value; });
      post('/lead', body).then(function (r) {
        if (r.ok) { f.replaceWith(el('div', 'ai-msg ai-assistant', 'Thank you. Our team will contact you within one working day.')); }
        else { b.disabled = false; system((r.data && r.data.message) || 'Please check your details.'); }
      });
    });
    return f;
  }

  function post(path, body) {
    return fetch(api + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) })
      .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, status: res.status, data: d }; }); })
      .catch(function () { return { ok: false, status: 0, data: null }; });
  }

  function apply(d) {
    token = d.token; status = d.status;
    (d.messages || []).forEach(render);
    save();
    if (status === 'needs_human' || status === 'human') startPoll(); else stopPoll();
  }

  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true; quick.hidden = true;
    render({ role: 'visitor', body: text });
    var typing = el('div', 'ai-msg ai-assistant ai-typing', 'Typing…'); list.appendChild(typing); list.scrollTop = list.scrollHeight;
    post('/message', { token: token, message: text, page: location.pathname, after: lastId }).then(function (r) {
      typing.remove(); busy = false;
      if (r.ok) {
        // Our own message comes back with an id: note it, but don't draw it twice.
        r.data.messages = (r.data.messages || []).filter(function (m) {
          if (m.role !== 'visitor') return true;
          if (m.id > lastId) lastId = m.id;
          return false;
        });
        apply(r.data);
      } else if (r.status === 429) {
        system('You are sending messages very fast. Please wait a moment.');
      } else {
        system('We could not reach the assistant. Call ' + phone + ' or leave your details.'); list.appendChild(leadForm());
      }
    });
  }

  function poll() {
    if (!token || doc.hidden) return;
    fetch(api + '/' + token + '?after=' + lastId, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) apply(d); }).catch(function () {});
  }
  function startPoll() { if (!pollTimer) pollTimer = setInterval(poll, 5000); }
  function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

  function open(prompt) {
    hideTeaser();
    panel.hidden = false; root.classList.add('is-open'); bubble.setAttribute('aria-expanded', 'true');
    if (!list.childNodes.length) {
      render({ role: 'assistant', body: prompt || 'Hello! I can help you get a quote, track a shipment or reach our team. What do you need?' });
      if (token) { lastId = 0; poll(); }
    }
    input.focus();
  }
  function close() { panel.hidden = true; root.classList.remove('is-open'); bubble.setAttribute('aria-expanded', 'false'); bubble.focus(); }

  bubble.addEventListener('click', function () { panel.hidden ? open(teaser.hidden ? null : $('[data-ai-teaser-text]').textContent) : close(); });
  $('[data-ai-close]').addEventListener('click', close);
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
  form.addEventListener('submit', function (e) { e.preventDefault(); var t = input.value; input.value = ''; send(t); });
  quick.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) send(b.getAttribute('data-say')); });
  doc.addEventListener('visibilitychange', function () { if (!doc.hidden && pollTimer) poll(); });

  /* Proactive prompts: once per session per trigger, never auto-opening the window. */
  function hideTeaser() { teaser.hidden = true; }
  function nudge(key, text) {
    if (off || !panel.hidden || !teaser.hidden) return;
    try { if (ss.getItem('gea-ai-n-' + key)) return; ss.setItem('gea-ai-n-' + key, '1'); } catch (e) {}
    $('[data-ai-teaser-text]').textContent = text; teaser.hidden = false;
  }
  $('[data-ai-teaser-open]').addEventListener('click', function () { open($('[data-ai-teaser-text]').textContent); });
  $('[data-ai-dismiss]').addEventListener('click', hideTeaser);
  $('[data-ai-never]').addEventListener('click', function () {
    off = true; hideTeaser(); doc.cookie = 'gea_ai_off=1; max-age=31536000; path=/; SameSite=Lax';
  });

  var path = location.pathname;
  if (path === '/') {
    var t = setTimeout(function () { nudge('home', 'Moving goods somewhere? I can get you a quote or track a shipment.'); }, 9000);
    window.addEventListener('scroll', function onS() {
      var max = doc.documentElement.scrollHeight - innerHeight;
      if (max > 0 && scrollY / max >= 0.4) { clearTimeout(t); window.removeEventListener('scroll', onS); nudge('home', 'Moving goods somewhere? I can get you a quote or track a shipment.'); }
    }, { passive: true });
  }
  if (path.indexOf('/services/') === 0) {
    var h = doc.querySelector('h1'), name = h ? h.textContent.trim().toLowerCase() : 'this service';
    setTimeout(function () { nudge('service', 'Questions about ' + name + '? Ask me, or get a quote in 1 minute.'); }, 6000);
  }
  var track = doc.getElementById('track-code');
  if (track) {
    var tt = null;
    track.addEventListener('focus', function () { tt = setTimeout(function () { nudge('track', 'Need help finding your tracking code?'); }, 12000); });
    if (track.form) track.form.addEventListener('submit', function () { clearTimeout(tt); });
  }
  if (matchMedia('(pointer: fine)').matches) {
    doc.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY <= 0) nudge('exit', 'Want our team to call you back instead?'); });
  }
  var quoteSec = doc.getElementById('quote');
  if (quoteSec && 'IntersectionObserver' in window) {
    var idle = null;
    new IntersectionObserver(function (es) {
      es.forEach(function (en) {
        clearTimeout(idle);
        if (en.isIntersecting) idle = setTimeout(function () { nudge('exit', 'Want our team to call you back instead?'); }, 60000);
      });
    }, { threshold: 0.3 }).observe(quoteSec);
  }
})();
