/* UDS Assistant (beta) — store chat widget. Signed-in customers only (the page only
   includes this script for them, and every endpoint is behind auth). */
(function () {
  var root = document.querySelector('[data-ua]');
  if (!root) return;

  var CSRF = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  var panel = root.querySelector('.ua-panel');
  var log = root.querySelector('[data-ua-log]');
  var input = root.querySelector('[data-ua-input]');
  var sendBtn = root.querySelector('[data-ua-send]');
  var left = root.querySelector('[data-ua-left]');
  var count = root.querySelector('[data-ua-count]');
  var MAX = parseInt(root.getAttribute('data-max') || '800', 10);
  var me = { email: '', name: '', avatar: '', bot: '', voice: false };
  /* lastId is only the poll cursor; seen holds every message id already drawn, so a row the shop wrote
     during a send (lower id than the answer) is still drawn once, and nothing is drawn twice */
  var loaded = false, busy = false, lastId = 0, seen = {}, mode = 'bot', pollT = null, pending = [];
  var launch = root.querySelector('.ua-launch');
  var photoIn = root.querySelector('[data-ua-photo-input]'), tray = root.querySelector('[data-ua-tray]'), banner = root.querySelector('[data-ua-banner]');

  var T = window.udsT || function (x, r) { if (r) for (var k in r) x = x.split(':' + k).join(r[k]); return x; };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* the assistant writes plain text with **bold** and "- " bullets; nothing else is rendered */
  function format(text) {
    var html = '', list = false;
    esc(text).split('\n').forEach(function (line) {
      var t = line.trim();
      var bold = function (s) { return s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>'); };
      if (/^[-•]\s+/.test(t)) {
        if (!list) { html += '<ul>'; list = true; }
        html += '<li>' + bold(t.replace(/^[-•]\s+/, '')) + '</li>';
      } else {
        if (list) { html += '</ul>'; list = false; }
        if (t) html += '<p>' + bold(t) + '</p>';
      }
    });
    if (list) html += '</ul>';
    return html;
  }

  /* follow new lines only while the reader is at the bottom; someone scrolled up to re-read stays put */
  var atBottom = true;
  log.addEventListener('scroll', function () { atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80; }, { passive: true });
  function scrollDown(force) { if (force || atBottom) { log.scrollTop = log.scrollHeight; atBottom = true; } }
  /* a tall answer: show its first line, not its last */
  function scrollToTop(node) {
    if (!node || !atBottom) return;
    var top = node.getBoundingClientRect().top - log.getBoundingClientRect().top + log.scrollTop - 8;
    if (log.scrollHeight - top > log.clientHeight) { log.scrollTop = top; atBottom = false; } else scrollDown();
  }
  function srText(text) { var s = document.createElement('span'); s.className = 'ua-sr'; s.textContent = text; return s; }

  /* one player for the whole chat: starting a clip stops the one before. A clip is a "Listen" button
     (.ua-play) or a voice note (.ua-vn); both carry data-src and show playing / progress. */
  var player = null, playing = null;
  var ICON_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
  var ICON_STOP = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 6h3.5v12H7zM13.5 6H17v12h-3.5z"/></svg>';
  function playBtn(src, label) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'ua-play'; b.setAttribute('data-src', src);
    b.innerHTML = '<i>' + ICON_PLAY + '</i><span>' + esc(label) + '</span>';
    b.addEventListener('click', function () { toggle(b); });
    return b;
  }
  function mmss(s) { s = Math.max(0, Math.round(s || 0)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  /* a voice note: play, a waveform that fills as it plays, the length, and the words one tap away */
  function voiceNote(src, text, seed, onFail) {
    var v = document.createElement('div'); v.className = 'ua-vn'; v.setAttribute('data-src', src); v._fail = onFail;
    var bars = '', x = (seed || 7) * 9301 + 49297;
    for (var i = 0; i < 30; i++) { x = (x * 9301 + 49297) % 233280; var h = 28 + Math.round((x / 233280) * 72); if (i < 3 || i > 26) h = Math.min(h, 45); bars += '<i style="height:' + h + '%"></i>'; }
    v.innerHTML = '<button type="button" class="ua-vn__play" aria-label="' + esc(T('Play voice note')) + '"><span>' + ICON_PLAY + '</span></button>' +
      '<div class="ua-vn__wave" aria-hidden="true">' + bars + '</div><span class="ua-vn__t">0:00</span>';
    var pb = v.querySelector('.ua-vn__play');
    pb.addEventListener('click', function () { toggle(v); });
    // the length, without downloading the whole clip first (the answer is spoken on this first request)
    var probe = new Audio(); probe.preload = 'metadata'; probe.src = src;
    probe.addEventListener('loadedmetadata', function () {
      if (!isFinite(probe.duration)) return;
      v.querySelector('.ua-vn__t').textContent = mmss(probe.duration); v._dur = probe.duration;
      pb.setAttribute('aria-label', T('Play voice note, :t', { t: mmss(probe.duration) }));
    });
    probe.addEventListener('error', function () { if (onFail) onFail(); });
    return v;
  }
  function setBtn(b, on) {
    if (!b) return;
    b.classList.remove('is-loading'); b.classList.toggle('is-playing', on);
    var ic = b.querySelector('.ua-vn__play span') || b.querySelector('i'); if (ic) ic.innerHTML = on ? ICON_STOP : ICON_PLAY;
    var pb = b.querySelector('.ua-vn__play') || b; pb.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  function progress(b, t, d) {
    if (!b || !b.classList.contains('ua-vn')) return;
    var bars = b.querySelectorAll('.ua-vn__wave i'), k = d ? t / d : 0;
    for (var i = 0; i < bars.length; i++) bars[i].classList.toggle('on', i / bars.length < k);
    b.querySelector('.ua-vn__t').textContent = mmss(d && t < d ? d - t : d);
  }
  function toggle(b) {
    if (playing === b && player && !player.paused) { player.pause(); setBtn(b, false); playing = null; return; }
    if (player) { player.pause(); setBtn(playing, false); }
    player = new Audio(b.getAttribute('data-src')); playing = b;
    b.classList.add('is-loading');
    var pl = player;
    pl.addEventListener('playing', function () { setBtn(b, true); });
    pl.addEventListener('timeupdate', function () { progress(b, pl.currentTime, isFinite(pl.duration) ? pl.duration : b._dur); });
    pl.addEventListener('ended', function () { setBtn(b, false); progress(b, 0, isFinite(pl.duration) ? pl.duration : b._dur); if (playing === b) playing = null; });
    pl.addEventListener('error', function () {
      setBtn(b, false); if (playing === b) playing = null;
      // a voice note that can't be spoken shows its words instead
      if (b.classList.contains('ua-vn')) { if (b._fail) b._fail(); return; }
      var l = b.querySelector('span'); if (l) l.textContent = T('Audio unavailable');
    });
    var p = pl.play(); if (p && p.catch) p.catch(function () { setBtn(b, false); });
  }

  function add(role, text, extra) {
    extra = extra || {};
    var el = document.createElement('div');
    el.className = 'ua-msg ua-msg--' + role;
    if (role === 'bot' && extra.format === 'voice' && extra.audio) {
      // a voice note; the words stay one tap away (and are what screen readers get)
      el.className += ' ua-msg--voice';
      var show = document.createElement('button'), words = document.createElement('div'), sr = srText(text);
      var vn = voiceNote(extra.audio, text, extra.id || text.length, function () {
        // no audio (voice down, generation failed): it becomes a plain text answer
        if (!vn.parentNode) return;
        vn.remove(); show.remove(); sr.remove(); words.hidden = false; el.classList.remove('ua-msg--voice');
      });
      el.appendChild(vn);
      // screen readers get the words straight away; sighted readers tap Show text
      el.appendChild(sr);
      show.type = 'button'; show.className = 'ua-vn__show'; show.textContent = T('Show text'); show.setAttribute('aria-expanded', 'false');
      words.className = 'ua-vn__text'; words.hidden = true; words.innerHTML = format(text);
      show.addEventListener('click', function () { words.hidden = !words.hidden; sr.hidden = !words.hidden; show.textContent = words.hidden ? T('Show text') : T('Hide text'); show.setAttribute('aria-expanded', words.hidden ? 'false' : 'true'); scrollDown(); });
      el.appendChild(show); el.appendChild(words);
    } else if (role === 'bot' || role === 'agent') {
      el.innerHTML = (role === 'agent' && extra.agent ? '<span class="ua-who">' + esc(extra.agent) + ' · UDS</span>' : '') + format(text);
      if (extra.audio && me.voice) el.appendChild(playBtn(extra.audio, T('Listen')));
    } else if (role === 'me' && extra.voice) {
      el.appendChild(playBtn(extra.audio || '', T('Voice message')));
      var heard = document.createElement('span'); heard.className = 'ua-heard' + (text ? '' : ' ua-heard--wait');
      heard.textContent = text || T('Transcribing…'); el.appendChild(heard);
    }
    else if (text && text !== '(photo)') el.textContent = text;
    if (extra.photos && extra.photos.length) {
      var g = document.createElement('div'); g.className = 'ua-photos';
      extra.photos.forEach(function (src) { var a = document.createElement('a'); a.href = src; a.target = '_blank'; a.rel = 'noopener'; a.innerHTML = '<img alt="" loading="lazy" src="' + esc(src) + '">'; g.appendChild(a); });
      el.appendChild(g);
    }
    // profile pictures: the customer's own (Account → Profile & security), the assistant's, or the shop person's
    var pic = role === 'me' ? me.avatar : (role === 'agent' ? (extra.agent_avatar || me.bot) : (role === 'bot' ? me.bot : ''));
    if (pic) {
      var row = document.createElement('div');
      row.className = 'ua-row ua-row--' + role;
      var img = document.createElement('img');
      img.className = 'ua-av'; img.alt = ''; img.width = 28; img.height = 28; img.src = pic; img.setAttribute('aria-hidden', 'true');
      row.appendChild(img); row.appendChild(el);
      log.appendChild(row); scrollDown(); return el;
    }
    log.appendChild(el); scrollDown(); return el;
  }

  /* ---- widgets under an answer, all drawn from data the server built ---- */
  function money(n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  var ICON = {
    box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>',
    truck: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    copy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>'
  };
  function pill(key, label) { return '<span class="ua-pill ua-pill--' + esc(key) + '">' + esc(T(label)) + '</span>'; }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  /* a tap that answers for the customer: sent like a typed message, then the buttons it came with go quiet */
  function tapSend(box, text, extra) { if (busy) return; box.classList.add('is-used'); box.querySelectorAll('button').forEach(function (b) { b.disabled = true; }); send(text, extra); }

  function wOrders(w, live) {
    var box = el('div', 'ua-w ua-w-orders');
    box.appendChild(el('div', 'ua-w__h', ICON.box + '<span>' + esc(T('Your orders')) + '</span>'));
    w.orders.forEach(function (o) {
      var b = el('button', 'ua-ord',
        '<span class="ua-ord__main"><b class="ua-mono">' + esc(o.number) + '</b><small>' + esc(o.date) + (o.items ? ' · ' + esc(o.items) : '') + '</small></span>' +
        '<span class="ua-ord__side"><b>' + money(o.total) + '</b>' + pill(o.status, o.status_label) + '</span>' + '<span class="ua-ord__go" aria-hidden="true">' + ICON.arrow + '</span>');
      b.type = 'button'; b.disabled = !live;
      b.addEventListener('click', function () { tapSend(box, T('Order :n', { n: o.number })); });
      box.appendChild(b);
    });
    return box;
  }

  function wTracking(w) {
    var box = el('div', 'ua-w ua-w-trk' + (w.trouble ? ' is-trouble' : '') + (w.stopped ? ' is-stopped' : ''));
    var cur = -1; (w.steps || []).forEach(function (s, i) { if (s.done) cur = i; });
    var headline = w.stopped ? T(w.status_label) : (w.steps[cur] ? T(w.steps[cur].label) : T(w.status_label));
    box.appendChild(el('div', 'ua-trk__top',
      '<span class="ua-trk__ic">' + (w.pickup ? ICON.box : ICON.truck) + '</span>' +
      '<span class="ua-trk__hd"><small>' + esc(T('Order')) + ' <span class="ua-mono">' + esc(w.number) + '</span></small><b>' + esc(headline) + '</b></span>' + pill(w.status, w.status_label)));
    if (w.steps && w.steps.length) {
      var pct = Math.max(0, cur) / (w.steps.length - 1) * 100;
      var bar = el('div', 'ua-trk__bar', '<span style="width:' + pct + '%"></span>');
      bar.setAttribute('role', 'progressbar'); bar.setAttribute('aria-valuemin', '0'); bar.setAttribute('aria-valuemax', String(w.steps.length - 1)); bar.setAttribute('aria-valuenow', String(Math.max(0, cur)));
      bar.setAttribute('aria-valuetext', headline);
      box.appendChild(bar);
      var ol = el('ol', 'ua-trk__steps');
      w.steps.forEach(function (s, i) {
        var li = el('li', (s.done ? 'is-done' : '') + (w.steps[i + 1] && w.steps[i + 1].done ? ' is-link' : '') + (i === cur && i < w.steps.length - 1 ? ' is-now' : ''),
          '<i>' + (s.done ? ICON.check : '') + '</i><span><b>' + esc(T(s.label)) + '</b>' + (s.at ? '<small>' + esc(s.at) + '</small>' : '') + '</span>');
        if (i === cur) li.setAttribute('aria-current', 'step');
        ol.appendChild(li);
      });
      box.appendChild(ol);
    }
    if (w.eta) box.appendChild(el('div', 'ua-trk__eta', ICON.cal + '<span>' + esc(T('Estimated delivery')) + ' <b>' + esc(w.eta) + '</b></span>'));
    if (w.tracking_number) {
      var c = el('div', 'ua-trk__num', '<span><small>' + esc(w.carrier) + '</small><b class="ua-mono">' + esc(w.tracking_number) + '</b></span>' +
        '<button type="button" class="ua-iconbtn ua-trk__copy" aria-label="' + esc(T('Copy tracking number')) + '">' + ICON.copy + '</button>');
      c.querySelector('button').addEventListener('click', function (e) {
        var b = e.currentTarget;
        try { navigator.clipboard.writeText(w.tracking_number).then(function () { b.innerHTML = ICON.check; setTimeout(function () { b.innerHTML = ICON.copy; }, 1500); }); } catch (x) {}
      });
      box.appendChild(c);
    }
    if (w.scans && w.scans.length) {
      var ul = el('ul', 'ua-trk__scans');
      w.scans.forEach(function (s, i) { ul.appendChild(el('li', i === 0 ? 'is-latest' : '', '<b>' + esc(T(s.label)) + '</b>' + (s.place ? '<span>' + ICON.pin + esc(s.place) + '</span>' : '') + (s.at ? '<time>' + esc(s.at) + '</time>' : ''))); });
      box.appendChild(ul);
    }
    var act = el('div', 'ua-w__act');
    if (w.pay_link) act.appendChild(el('a', 'ua-wbtn ua-wbtn--pri', esc(T('Pay now')))).href = w.pay_link;
    if (w.tracking_url) { var t = act.appendChild(el('a', 'ua-wbtn' + (w.pay_link ? '' : ' ua-wbtn--pri'), esc(T('Track with :c', { c: w.carrier })))); t.href = w.tracking_url; t.target = '_blank'; t.rel = 'noopener'; }
    act.appendChild(el('a', 'ua-wbtn', esc(T('Order details')))).href = w.page;
    box.appendChild(act);
    return box;
  }

  function wConfirm(w, live) {
    var box = el('div', 'ua-w ua-w-sum');
    box.appendChild(el('div', 'ua-w__h', ICON.box + '<span>' + esc(T('Order summary')) + '</span>'));
    var lines = el('ul', 'ua-sum__lines');
    w.items.forEach(function (i) { lines.appendChild(el('li', '', '<span><b class="ua-mono">' + esc(i.sku) + '</b> <small>× ' + i.qty + '</small><em>' + esc(i.name) + '</em></span><b>' + money(i.line) + '</b>')); });
    box.appendChild(lines);
    var tot = '<dl class="ua-sum__tot"><div><dt>' + esc(T('Subtotal')) + '</dt><dd>' + money(w.subtotal) + '</dd></div>' +
      (w.discount ? '<div class="is-off"><dt>' + esc(w.discount.label) + '</dt><dd>−' + money(Math.abs(w.discount.amount)) + '</dd></div>' : '') +
      '<div><dt>' + esc(w.shipping.label || T('Shipping')) + '</dt><dd>' + (w.shipping.cost ? money(w.shipping.cost) : esc(T('Free'))) + '</dd></div>' +
      '<div class="is-total"><dt>' + esc(T('Total')) + '</dt><dd>' + money(w.total) + '</dd></div></dl>';
    box.appendChild(el('div', '', tot));
    box.appendChild(el('div', 'ua-sum__meta', '<div><small>' + esc(T('Ship to')) + '</small><span>' + esc(w.ship_to) + '</span></div><div><small>' + esc(T('Payment')) + '</small><span>' + esc(w.payment) + '</span></div>' + (w.po ? '<div><small>PO</small><span class="ua-mono">' + esc(w.po) + '</span></div>' : '')));
    var act = el('div', 'ua-w__act ua-w__act--full');
    var yes = act.appendChild(el('button', 'ua-wbtn ua-wbtn--pri', ICON.check + esc(T(w.yes)))), no = act.appendChild(el('button', 'ua-wbtn', esc(T(w.no))));
    // a summary is good for 20 minutes (the server forgets the draft then); an expired card can't be confirmed
    var left = w.expires_at ? w.expires_at * 1000 - Date.now() : Infinity;
    var expire = function () { yes.disabled = no.disabled = true; box.classList.add('is-used'); if (!box.querySelector('.ua-sum__exp')) box.appendChild(el('p', 'ua-sum__exp', esc(T('This summary has expired — ask again to get a fresh one.')))); };
    yes.type = no.type = 'button'; yes.disabled = no.disabled = !live || left <= 0;
    yes.addEventListener('click', function () { if (w.expires_at && Date.now() > w.expires_at * 1000) return expire(); tapSend(box, T(w.yes), w.nonce ? { confirm: w.nonce } : null); });
    no.addEventListener('click', function () { tapSend(box, T(w.no)); });
    box.appendChild(act);
    if (live && left <= 0) expire();
    else if (live && isFinite(left)) setTimeout(function () { if (!box.classList.contains('is-used')) expire(); }, Math.min(left, 2147483000));
    return box;
  }

  function wPlaced(w) {
    var box = el('div', 'ua-w ua-w-done');
    box.appendChild(el('div', 'ua-done__top', '<span class="ua-done__ic">' + ICON.check + '</span><span><b>' + esc(T('Order placed')) + '</b><small class="ua-mono">' + esc(w.number) + ' · ' + money(w.total) + '</small></span>'));
    var msg = w.payment === 'awaiting_payment' || w.payment === 'needs_customer_confirmation' || w.payment === 'card_failed' ? T('Waiting for payment — use the button to pay securely.')
      : w.payment === 'processing' ? T('Your card payment is processing. We e-mail the confirmation as soon as it clears.')
      : (w.payment === 'invoice' ? T('Invoiced on your terms. A confirmation is on its way by e-mail.') : T('Paid. A confirmation is on its way by e-mail.'));
    box.appendChild(el('p', 'ua-done__msg', esc(msg)));
    var act = el('div', 'ua-w__act');
    if (w.pay_link) act.appendChild(el('a', 'ua-wbtn ua-wbtn--pri', esc(T('Pay now')))).href = w.pay_link;
    act.appendChild(el('a', 'ua-wbtn' + (w.pay_link ? '' : ' ua-wbtn--pri'), esc(T('View order')))).href = w.page;
    box.appendChild(act);
    return box;
  }

  function wChoices(w, live) {
    var box = el('div', 'ua-quick');
    w.options.forEach(function (o) {
      var b = el('button', 'ua-chip', esc(o)); b.type = 'button'; b.disabled = !live;
      b.addEventListener('click', function () { tapSend(box, o); box.remove(); });
      box.appendChild(b);
    });
    return box;
  }

  /* the guided driveshaft build (driveshaft_builder): the server sends every label already in the customer's language */
  var ICON_SHAFT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10v4M6 9v6M6 12h12M18 9v6M21 10v4"/></svg>';
  function wBuildChoices(w, live) {
    var box = el('div', 'ua-w ua-w-bopts');
    if (w.stage) box.appendChild(el('div', 'ua-w__h', ICON_SHAFT + '<span>' + esc(w.stage) + '</span>'));
    var list = el('div', 'ua-bopts');
    w.options.forEach(function (o) {
      var price = o.price != null ? (o.price > 0 ? '+' + money(o.price) : money(0)) : (o.from != null ? esc((w.labels && w.labels.from || 'from :price').replace(':price', money(o.from))) : '');
      var b = el('button', 'ua-bopt', '<span class="ua-bopt__main"><b>' + esc(o.label) + '</b>' + (o.note ? '<small>' + esc(o.note) + '</small>' : '') + '</span>' + (price ? '<span class="ua-bopt__price ua-mono">' + price + '</span>' : '') + '<span class="ua-ord__go" aria-hidden="true">' + ICON.arrow + '</span>');
      b.type = 'button'; b.disabled = !live;
      b.addEventListener('click', function () { b.classList.add('is-on'); tapSend(box, o.label); });
      list.appendChild(b);
    });
    box.appendChild(list);
    return box;
  }
  function wBuild(w, live) {
    var L = w.labels || {};
    var box = el('div', 'ua-w ua-w-build');
    if (w.photo) { var f = box.appendChild(el('figure', 'ua-build__ph', '<img src="' + esc(w.photo) + '" alt="" loading="lazy"><figcaption>' + esc(L.photo || '') + '</figcaption>')); f.querySelector('img').addEventListener('error', function () { f.remove(); }); }
    box.appendChild(el('div', 'ua-build__hd', '<b>' + esc(w.title) + '</b>' + (w.fits ? '<small>' + esc((L.fits || 'Fits') + ': ' + w.fits) + '</small>' : '')));
    if (w.specs && w.specs.length) {
      var dl = el('dl', 'ua-build__specs');
      w.specs.forEach(function (r) { dl.appendChild(el('div', '', '<dt>' + esc(r.q) + '</dt><dd>' + esc(r.a) + '</dd>')); });
      box.appendChild(dl);
    }
    var lines = el('ul', 'ua-sum__lines');
    (w.lines || []).forEach(function (l) { lines.appendChild(el('li', '', '<span>' + esc(l.label) + '</span><b>' + money(l.usd) + '</b>')); });
    box.appendChild(lines);
    box.appendChild(el('dl', 'ua-sum__tot', '<div class="is-total"><dt>' + esc(L.total || 'Total') + '</dt><dd>' + money(w.price) + '</dd></div>'));
    if (w.speed) box.appendChild(el('p', 'ua-build__speed', '<b>' + esc(L.speed || 'Speed check') + '</b> ' + esc(w.speed)));
    var act = el('div', 'ua-w__act ua-w__act--full');
    var add = act.appendChild(el('button', 'ua-wbtn ua-wbtn--pri', ICON.check + esc(L.add || 'Add to cart')));
    add.type = 'button'; add.disabled = !live;
    add.addEventListener('click', function () { tapSend(box, L.add_say || L.add || 'Add it to my cart'); });
    var open = act.appendChild(el('a', 'ua-wbtn', esc(L.open || 'Open in the builder'))); open.href = w.link; open.target = '_blank'; open.rel = 'noopener';
    box.appendChild(act);
    return box;
  }
  function wBuildAdded(w) {
    var L = w.labels || {};
    var box = el('div', 'ua-w ua-w-done');
    box.appendChild(el('div', 'ua-done__top', '<span class="ua-done__ic">' + ICON.check + '</span><span><b>' + esc(L.done || 'Added to your cart') + '</b><small>' + esc(w.title) + ' · ' + (w.qty > 1 ? w.qty + ' × ' : '') + money(w.price) + '</small></span>'));
    var act = el('div', 'ua-w__act');
    act.appendChild(el('a', 'ua-wbtn ua-wbtn--pri', esc(L.checkout || 'Checkout'))).href = w.checkout;
    act.appendChild(el('a', 'ua-wbtn', esc(L.cart || 'View cart'))).href = w.cart;
    box.appendChild(act);
    return box;
  }
  function wBuildLink(w) {
    var box = el('div', 'ua-w ua-w-blink');
    if (w.stage) box.appendChild(el('div', 'ua-w__h', ICON_SHAFT + '<span>' + esc(w.stage) + '</span>'));
    if (w.text) box.appendChild(el('p', 'ua-build__note', esc(w.text)));
    var act = el('div', 'ua-w__act');
    var a = act.appendChild(el('a', 'ua-wbtn', esc((w.labels || {}).open || 'Open'))); a.href = w.link; a.target = '_blank'; a.rel = 'noopener';
    box.appendChild(act);
    return box;
  }

  function widgets(list, live) {
    (list || []).forEach(function (w) {
      var node = null;
      try {
        if (w.type === 'orders') node = wOrders(w, live);
        else if (w.type === 'tracking') node = wTracking(w);
        else if (w.type === 'confirm') node = wConfirm(w, live);
        else if (w.type === 'placed') node = wPlaced(w);
        else if (w.type === 'choices') { if (!live) return; node = wChoices(w, live); }
        else if (w.type === 'build_choices') { if (!live) return; node = wBuildChoices(w, live); }
        else if (w.type === 'build') node = wBuild(w, live);
        else if (w.type === 'build_added') node = wBuildAdded(w);
        else if (w.type === 'build_link') { if (!live) return; node = wBuildLink(w); }
      } catch (e) { node = null; }
      if (node) { log.appendChild(node); scrollDown(); }
    });
  }

  /* one stored message (history, or something new from the shop) */
  function render(m, live) {
    if (m.id && seen[m.id]) return;
    if (m.id) { seen[m.id] = 1; lastId = Math.max(lastId, m.id); }
    if (m.role === 'system') { var s1 = document.createElement('div'); s1.className = 'ua-sys'; s1.textContent = m.text; log.appendChild(s1); scrollDown(); return; }
    // a part card or a tracking card the shop sent on its own: no empty bubble above it
    if (!(m.role === 'agent' && !m.text && !(m.photos || []).length)) add(m.role === 'user' ? 'me' : (m.role === 'agent' ? 'agent' : 'bot'), m.text, m);
    if (root.classList.contains('is-open')) markSeen();
    if (m.cards && m.cards.length) cards(m.cards);
    if (m.widgets && m.widgets.length) widgets(m.widgets, !!live);
  }
  /* a whole history: only the newest answer's buttons still work */
  function renderAll(list) {
    var last = list.length ? list[list.length - 1] : null;
    list.forEach(function (m) { render(m, m === last); });
  }

  /* a fresh answer from send(): text or voice note, then the part cards and widgets */
  function answer(d, autoplay) {
    var follow = atBottom;
    if (d.answer_id) seen[d.answer_id] = 1;
    var node = add('bot', d.text, { format: d.format, audio: d.audio, id: d.answer_id });
    cards(d.cards);
    widgets(d.widgets, true);
    // a long answer: start reading at its top
    if (follow && node) { atBottom = true; scrollToTop(node.parentNode && node.parentNode.classList.contains('ua-row') ? node.parentNode : node); }
    var clip = node && (node.querySelector('.ua-vn') || null);
    // a voice note answering a voice message plays straight away; a text answer just offers Listen
    if (clip && (autoplay || d.autoplay)) toggle(clip);
    return node;
  }

  function setMode(md, agent) {
    mode = md || 'bot';
    if (banner) { banner.hidden = mode !== 'human'; if (mode === 'human') banner.textContent = T('You are chatting with :name from Universal Driveshafts.', { name: agent || T('someone') }); }
  }

  /* someone at the shop may take the chat over at any time: check for new lines while the panel is open */
  function poll() {
    clearTimeout(pollT);
    if (!root.classList.contains('is-open')) return;
    req('GET', '/assistant/poll?after=' + lastId).then(function (d) {
      // while a message is on its way the server already holds it (and maybe the answer): the send()
      // response draws both and moves lastId past them, so drawing them here showed every message twice
      if (busy) return;
      (d.messages || []).forEach(function (m) { render(m, true); });
      if (d.mode) setMode(d.mode, d.agent);
    }).catch(function () {}).then(function () { pollT = setTimeout(poll, mode === 'human' ? 4000 : 12000); });
  }

  function cards(list) {
    if (!list || !list.length) return;
    var row = document.createElement('div');
    row.className = 'ua-cards';
    row.setAttribute('aria-label', T('Parts'));
    list.slice(0, 6).forEach(function (c) {
      if (!c.url) return;
      var a = document.createElement('a');
      a.className = 'ua-card'; a.href = c.url;
      var st = c.stock === 'in stock' ? 'in' : (c.stock === 'out of stock' ? 'out' : '');
      a.innerHTML =
        (c.image ? '<div class="ua-card__img" role="img" aria-label="' + esc(c.sku) + '"></div>'
                 : '<div class="ua-card__img ua-card__img--none" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg></div>') +
        '<div class="ua-card__body"><span class="ua-card__sku">' + esc(c.sku) + '</span>' +
        '<span class="ua-card__name">' + esc(c.name) + '</span>' +
        '<span class="ua-card__foot">' + (c.price_usd ? '<span class="ua-card__price">$' + Number(c.price_usd).toFixed(2) + '</span>' : '<span></span>') +
        (st ? '<span class="ua-card__stock ua-card__stock--' + st + '">' + (st === 'in' ? T('In stock') : T('Out')) + '</span>' : '') + '</span></div>';
      // set through the DOM: a URL can't break out of a style attribute
      if (c.image) a.querySelector('.ua-card__img').style.backgroundImage = 'url(' + JSON.stringify(String(c.image)) + ')';
      row.appendChild(a);
    });
    if (row.children.length) { log.appendChild(row); scrollDown(); }
  }

  function hello() {
    var h = document.createElement('div');
    h.className = 'ua-hello';
    h.innerHTML = '<h3>' + (me.name ? T('Hi :name, how can I help?', {name: esc(me.name)}) : T('Hi, how can I help?')) + '</h3>' +
      '<p>' + T('Ask about parts, fitment, shipping or your orders. Prices and stock come live from our catalog.') + '</p>' +
      '<div class="ua-chips">' +
      [T('Find a 1310 u-joint'), T('Driveshaft for my truck'), T('Where is my order?'), T('How does freight shipping work?')]
        .map(function (q) { return '<button type="button" class="ua-chip" data-ua-ask="' + esc(q) + '">' + esc(q) + '</button>'; }).join('') +
      '</div>';
    log.appendChild(h);
  }

  function typing(on) {
    var t = log.querySelector('.ua-typing');
    if (on && !t) { t = document.createElement('div'); t.className = 'ua-typing'; t.setAttribute('role', 'status'); t.innerHTML = '<i aria-hidden="true"></i><i aria-hidden="true"></i><i aria-hidden="true"></i>'; t.appendChild(srText(T('Assistant is typing'))); log.appendChild(t); scrollDown(); }
    if (!on && t) t.remove();
  }

  function setLeft(n) {
    if (!left) return;
    var box = left.parentNode;
    if (typeof n === 'number') { left.textContent = n; box.hidden = false; } else box.hidden = true;   // null = no limit (staff)
  }

  function req(method, url, body) {
    return fetch(url, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { d._status = r.status; return d; });
    });
  }

  function load() {
    if (loaded) return; loaded = true;
    req('GET', '/assistant').then(function (d) {
      if (!d.enabled) { add('err', T('The assistant is not available right now. Call (813) 524-6491 or use the contact page.')); input.disabled = sendBtn.disabled = true; return; }
      me.email = d.email || ''; me.name = d.name || ''; me.avatar = d.avatar || ''; me.bot = d.bot_avatar || '';
      me.voice = !!d.voice;
      if (micBtn) micBtn.hidden = !(me.voice && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
      setLeft(d.left_today);
      setMode(d.mode, d.agent);
      if (d.messages && d.messages.length) renderAll(d.messages);
      else hello();
      poll();
    }).catch(function () { add('err', T('Could not reach the assistant. Check your connection.')); });
  }

  /* after an answer: back to the input on desktop, or if it had focus (no keyboard popping up on phones otherwise) */
  var finePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
  function refocus(had) { if (had || finePointer) input.focus(); }
  /* the ids a send created: drawn already (optimistically or by answer()); polling resumes from the customer's own message */
  function took(d) {
    (d.ids || []).forEach(function (id) { seen[id] = 1; });
    if (d.user_id) { seen[d.user_id] = 1; lastId = Math.max(lastId, d.user_id); }
  }

  function send(text, extra) {
    text = (text || '').trim();
    if ((!text && !pending.length) || busy) return;
    if (text.length > MAX) { add('err', T('Please keep it under :max characters.', {max: MAX})); return; }
    var had = document.activeElement === input;
    var h = log.querySelector('.ua-hello'); if (h) h.remove();
    atBottom = true;
    add('me', text, { photos: pending.map(function (p) { return p.url; }) });
    var shots = pending.map(function (p) { return p.data; }).filter(Boolean);
    pending = []; renderTray();
    input.value = ''; grow(); counter();
    busy = true; sendBtn.disabled = true; if (mode !== 'human') typing(true);
    var body = { message: text, photos: shots };
    if (extra && extra.confirm) body.confirm = extra.confirm;
    req('POST', '/assistant', body)
      .then(function (d) {
      typing(false);
      took(d);
      if (d.human) { setMode('human', d.agent); return; }   // a person from the shop answers in this chat
      if (d._status >= 400 || d.error) {
        add('err', d.error || (d._status === 419 ? T('Your session expired — reload the page.') : d._status === 429 ? T('Slow down a little — try again in a moment.') : T('Something went wrong. Try again.')));
        if (d.restart) offerRestart();
        return;
      }
      answer(d, false);
      setLeft(d.left_today);
      // the assistant put something in the cart: update the header badge and the slide-in cart
      if (typeof d.cart_count === 'number') {
        document.querySelectorAll('[data-cart-count]').forEach(function (b) { b.textContent = d.cart_count; b.hidden = !d.cart_count; });
        if (window.UDSCart) window.UDSCart.refresh();
      }
      if (d.handoff) agentForm(d.summary || '');
    }).catch(function () { typing(false); add('err', T('Could not reach the assistant. Check your connection.')); })
      .then(function () { busy = false; sendBtn.disabled = !input.value.trim() && !pending.length; refocus(had); poll(); });
  }

  function offerRestart() {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'ua-chip'; b.style.alignSelf = 'center'; b.textContent = T('Start a new chat');
    b.addEventListener('click', restart); log.appendChild(b); scrollDown();
  }

  function restart() {
    if (histBox && !histBox.hidden) histShow(false);
    req('POST', '/assistant/new').then(function () { log.innerHTML = ''; lastId = 0; seen = {}; setMode('bot'); hello(); input.focus(); });
  }

  function agentForm(summary) {
    if (log.querySelector('.ua-agent')) { log.querySelector('.ua-agent textarea').focus(); return; }
    var f = document.createElement('form');
    f.className = 'ua-agent'; f.noValidate = true;
    var opened = Date.now();
    f.innerHTML =
      '<h4>' + T('Talk to a person') + '</h4><p>' + T('Someone from our shop will reply by e-mail, usually the same business day.') + '</p>' +
      '<label for="ua-a-email">' + T('E-mail') + '</label><input id="ua-a-email" name="email" type="email" autocomplete="email" required value="' + esc(me.email) + '">' +
      '<div class="ua-agent__row"><div><label for="ua-a-name">' + T('Name') + '</label><input id="ua-a-name" name="name" autocomplete="name" value="' + esc(me.name) + '"></div>' +
      '<div><label for="ua-a-phone">' + T('Phone') + ' <span style="font-weight:400;color:var(--ink-faint)">' + T('(optional)') + '</span></label><input id="ua-a-phone" name="phone" type="tel" autocomplete="tel"></div></div>' +
      '<label for="ua-a-req">' + T('What do you need?') + '</label><textarea id="ua-a-req" name="request" required minlength="10" maxlength="2000" placeholder="' + T('Part number, vehicle, measurements, quantity…') + '">' + esc(summary) + '</textarea>' +
      '<div class="ua-agent__hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>' +
      '<div class="ua-agent__act"><button type="button" class="ua-btn ua-btn--ghost" data-cancel>' + T('Cancel') + '</button><button class="ua-btn ua-btn--pri">' + T('Send to the shop') + '</button></div>';
    f.querySelector('[data-cancel]').addEventListener('click', function () { f.remove(); input.focus(); });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = f.querySelector('.ua-btn--pri');
      var data = { email: f.email.value.trim(), name: f.name.value.trim(), phone: f.phone.value.trim(), request: f.request.value.trim(), website: f.website.value, t: Math.round((Date.now() - opened) / 1000) };
      if (!/^\S+@\S+\.\S+$/.test(data.email)) { f.email.focus(); return; }
      if (data.request.length < 10) { f.request.focus(); return; }
      btn.disabled = true; btn.textContent = T('Sending…');
      req('POST', '/assistant/agent', data).then(function (d) {
        if (d._status >= 400 || d.error) { btn.disabled = false; btn.textContent = T('Send to the shop'); add('err', d.error || T('Could not send. Check the form and try again.')); return; }
        var ok = document.createElement('div');
        ok.className = 'ua-done';
        ok.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>' + T('Sent. We\'ll reply to <b>:email</b>.', {email: esc(data.email)}) + '</span>';
        f.replaceWith(ok); scrollDown();
      }).catch(function () { btn.disabled = false; btn.textContent = T('Send to the shop'); add('err', T('Could not reach the shop. Try again.')); });
    });
    log.appendChild(f); scrollDown();
    f.querySelector(me.email ? 'textarea' : 'input').focus();
  }

  /* photos waiting to be sent with the next message */
  function renderTray() {
    if (!tray) return;
    tray.innerHTML = ''; tray.hidden = !pending.length;
    pending.forEach(function (p, i) {
      var d = document.createElement('div'); d.className = 'ua-thumb';
      d.innerHTML = '<img alt="" src="' + p.url + '"><button type="button" aria-label="' + esc(T('Remove photo')) + '">×</button>';
      d.querySelector('button').addEventListener('click', function () { pending.splice(i, 1); renderTray(); sendBtn.disabled = !input.value.trim() && !pending.length; });
      tray.appendChild(d);
    });
    sendBtn.disabled = busy || (!input.value.trim() && !pending.length);
  }
  /* shrink a photo in the browser to a JPEG no larger than 1600 px before it is sent */
  function shrink(file) {
    return new Promise(function (ok, bad) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var k = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight)), c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        ok({ url: url, data: c.toDataURL('image/jpeg', 0.85) });
      };
      img.onerror = function () { URL.revokeObjectURL(url); bad(); };
      img.src = url;
    });
  }
  if (photoIn) photoIn.addEventListener('change', function () {
    var files = Array.prototype.slice.call(photoIn.files || []).slice(0, 3 - pending.length);
    photoIn.value = '';
    files.forEach(function (f) {
      if (f.size > 25 * 1024 * 1024) { add('err', T('That photo is over 25 MB.')); return; }
      shrink(f).then(function (p) { if (pending.length < 3) { pending.push(p); renderTray(); } })
        .catch(function () { add('err', T("We couldn't read that photo. Try a JPG or PNG.")); });
    });
    input.focus();
  });

  /* voice messages: record in the browser, send the clip; the server transcribes it and answers out loud */
  var micBtn = root.querySelector('[data-ua-mic]'), recBar = root.querySelector('[data-ua-rec]'), recT = root.querySelector('[data-ua-rec-t]');
  var compose = root.querySelector('.ua-compose'), rec = null, recStream = null, recChunks = [], recTimer = null, recStart = 0, recSend = false;
  var MAX_REC = 120;
  function recMime() {
    var opts = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];
    for (var i = 0; i < opts.length; i++) if (window.MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(opts[i])) return opts[i];
    return '';
  }
  function recUi(on) {
    recBar.hidden = !on; compose.hidden = on;
    if (on) { recT.textContent = '0:00'; recBar.querySelector('[data-ua-rec-send]').focus(); }
  }
  function recStop(sendIt) {
    recSend = sendIt; clearInterval(recTimer);
    if (rec && rec.state !== 'inactive') rec.stop(); else recUi(false);
  }
  function recBegin() {
    if (busy || rec) return;
    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (stream) {
      recStream = stream; recChunks = [];
      var mt = recMime();
      try { rec = mt ? new MediaRecorder(stream, { mimeType: mt, audioBitsPerSecond: 32000 }) : new MediaRecorder(stream); }
      catch (e) { rec = new MediaRecorder(stream); }
      rec.ondataavailable = function (e) { if (e.data && e.data.size) recChunks.push(e.data); };
      rec.onstop = function () {
        recStream.getTracks().forEach(function (t) { t.stop(); });
        var blob = new Blob(recChunks, { type: (rec.mimeType || 'audio/webm').split(';')[0] });
        var secs = (Date.now() - recStart) / 1000;
        rec = null; recUi(false);
        if (!recSend) { input.focus(); return; }
        if (secs < 0.8 || blob.size < 800) { add('err', T('That was too short — hold on a little longer while you speak.')); return; }
        var fr = new FileReader();
        fr.onload = function () { sendVoice(fr.result, URL.createObjectURL(blob)); };
        fr.readAsDataURL(blob);
      };
      rec.start(250); recStart = Date.now(); recUi(true);
      recTimer = setInterval(function () {
        var s = Math.floor((Date.now() - recStart) / 1000);
        recT.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        if (s >= MAX_REC) recStop(true);
      }, 250);
    }).catch(function () { add('err', T('Allow the microphone in your browser to send a voice message.')); });
  }
  function sendVoice(dataUrl, localUrl) {
    if (busy) return;
    var h = log.querySelector('.ua-hello'); if (h) h.remove();
    var bubble = add('me', '', { voice: true, audio: localUrl });
    busy = true; sendBtn.disabled = true; if (micBtn) micBtn.disabled = true; if (mode !== 'human') typing(true);
    req('POST', '/assistant', { voice: dataUrl, message: '' }).then(function (d) {
      typing(false);
      var heard = bubble.querySelector('.ua-heard');
      if (d.me && heard) { heard.textContent = d.me.text; heard.className = 'ua-heard'; }
      took(d);
      if (d.human) { setMode('human', d.agent); return; }
      if (d._status >= 400 || d.error) {
        if (heard) heard.remove();
        // ElevenLabs is down: no mic until the next page load
        if (d.voice === false && micBtn) { me.voice = false; micBtn.hidden = true; }
        add('err', d.error || (d._status === 429 ? T('Slow down a little — try again in a moment.') : T('Something went wrong. Try again.')));
        if (d.restart) offerRestart();
        return;
      }
      answer(d, true);
      setLeft(d.left_today);
      if (typeof d.cart_count === 'number') {
        document.querySelectorAll('[data-cart-count]').forEach(function (b) { b.textContent = d.cart_count; b.hidden = !d.cart_count; });
        if (window.UDSCart) window.UDSCart.refresh();
      }
      if (d.handoff) agentForm(d.summary || '');
    }).catch(function () { typing(false); add('err', T('Could not reach the assistant. Check your connection.')); })
      .then(function () { busy = false; if (micBtn) micBtn.disabled = false; sendBtn.disabled = !input.value.trim() && !pending.length; poll(); });
  }
  if (micBtn) micBtn.addEventListener('click', recBegin);
  root.querySelector('[data-ua-rec-cancel]').addEventListener('click', function () { recStop(false); });
  root.querySelector('[data-ua-rec-send]').addEventListener('click', function () { recStop(true); });

  function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; }
  function counter() { var n = input.value.length; count.textContent = n > MAX * 0.8 ? (MAX - n) + ' left' : ''; }

  function open() {
    root.classList.add('is-open');
    panel.setAttribute('aria-hidden', 'false');
    panel.inert = false; launch.setAttribute('aria-expanded', 'true');
    if (loaded) poll(); else load();
    markSeen();
    setTimeout(function () { grow(); input.focus(); }, 60);
    try { sessionStorage.setItem('ua.open', '1'); } catch (e) {}
  }
  function close() {
    if (rec) recStop(false);
    if (player) { player.pause(); setBtn(playing, false); }
    root.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    // a closed panel is out of the tab order and the accessibility tree
    panel.inert = true; launch.setAttribute('aria-expanded', 'false');
    launch.focus();
    try { sessionStorage.removeItem('ua.open'); } catch (e) {}
  }

  /* chat history: the customer's chats from the last 15 days, read-only */
  var histBox = root.querySelector('[data-ua-histbox]'), histList = root.querySelector('[data-ua-hist-list]'), histView = root.querySelector('[data-ua-hist-view]'), histTitle = root.querySelector('[data-ua-hist-title]');
  var histTitleHtml = histTitle ? histTitle.innerHTML : '';
  function histShow(on) { histBox.hidden = !on; panel.classList.toggle('is-hist', on); if (!on) { histView.hidden = true; histList.hidden = false; histTitle.innerHTML = histTitleHtml; scrollDown(); } }
  function histOpen() {
    if (player) { player.pause(); setBtn(playing, false); }
    histShow(true); histView.hidden = true; histList.hidden = false; histTitle.innerHTML = histTitleHtml;
    histList.innerHTML = '<p class="ua-hist__empty">' + esc(T('Loading…')) + '</p>';
    req('GET', '/assistant/history').then(function (d) {
      var chats = d.chats || [];
      if (!chats.length) { histList.innerHTML = '<p class="ua-hist__empty">' + esc(T('No chats in the last 15 days.')) + '</p>'; return; }
      histList.innerHTML = '';
      chats.forEach(function (c) {
        var n = c.messages === 1 ? T('1 message') : T(':n messages', { n: c.messages });
        // today: "2 hours ago"; older: the date
        var b = el('button', 'ua-hist__item', '<b>' + esc(c.title || T('Chat')) + '</b><small><span>' + esc(c.today ? c.when : c.date) + '</span><span>' + esc(n) + '</span>' + (c.open ? pill('delivered', 'Current') : '') + '</small>');
        b.type = 'button';
        b.addEventListener('click', function () { if (c.open) { histShow(false); return; } histRead(c); });
        histList.appendChild(b);
      });
    }).catch(function () { histList.innerHTML = '<p class="ua-hist__empty">' + esc(T('Could not load your chats. Try again.')) + '</p>'; });
  }
  /* draw a past chat with the same renderers, pointed at the history view (its buttons stay inactive) */
  function histRead(c) {
    histList.hidden = true; histView.hidden = false; histView.innerHTML = '<p class="ua-hist__empty">' + esc(T('Loading…')) + '</p>';
    histTitle.textContent = c.title || T('Chat');
    req('GET', '/assistant/history/' + c.id).then(function (d) {
      var keepLog = log, keepId = lastId, keepSeen = seen;
      histView.innerHTML = '';
      log = histView; lastId = 0; seen = {};
      try { (d.messages || []).forEach(function (m) { render(m, false); }); } finally { log = keepLog; lastId = keepId; seen = keepSeen; }
      histView.appendChild(el('p', 'ua-hist__note', esc(T('This chat has ended. Start a question in your current chat.'))));
      histView.scrollTop = 0;
    }).catch(function () { histView.innerHTML = '<p class="ua-hist__empty">' + esc(T('Could not load this chat.')) + '</p>'; });
  }
  root.querySelector('[data-ua-hist]').addEventListener('click', function () { if (!loaded) load(); if (histBox.hidden) histOpen(); else histShow(false); });
  root.querySelector('[data-ua-hist-back]').addEventListener('click', function () { if (!histView.hidden) { histView.hidden = true; histList.hidden = false; histTitle.innerHTML = histTitleHtml; } else histShow(false); });

  panel.inert = true; launch.setAttribute('aria-expanded', 'false');
  launch.addEventListener('click', open);
  root.querySelector('[data-ua-close]').addEventListener('click', close);
  root.querySelector('[data-ua-new]').addEventListener('click', restart);
  root.querySelector('[data-ua-person]').addEventListener('click', function () { var h = log.querySelector('.ua-hello'); if (h) h.remove(); agentForm(''); });
  log.addEventListener('click', function (e) { var c = e.target.closest('[data-ua-ask]'); if (c) send(c.getAttribute('data-ua-ask')); });
  sendBtn.addEventListener('click', function () { send(input.value); });
  input.addEventListener('input', function () { grow(); counter(); sendBtn.disabled = busy || (!input.value.trim() && !pending.length); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('is-open')) close(); });

  /* presence for the shop's chat page: where the customer is, only while this tab is visible. The answer says
     whether the shop wrote since the last line this browser drew, so a closed panel shows a badge. */
  var pingT = null;
  function seenMax() { var n = lastId; try { n = Math.max(n, parseInt(localStorage.getItem('ua.seen') || '0', 10) || 0); } catch (e) {} return n; }
  function markSeen() {
    try { localStorage.setItem('ua.seen', String(seenMax())); } catch (e) {}
    launch.classList.remove('has-unread'); launch.removeAttribute('title');
  }
  function ping() {
    clearTimeout(pingT);
    if (document.visibilityState !== 'visible') return;
    req('POST', '/assistant/ping', { url: location.pathname + location.search, title: document.title.slice(0, 190), after: seenMax() }).then(function (d) {
      if (d.unread > 0 && !root.classList.contains('is-open')) { launch.classList.add('has-unread'); launch.title = T('New message from Universal Driveshafts'); }
    }).catch(function () {}).then(function () { pingT = setTimeout(ping, 45000); });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') ping(); });
  ping();

  /* JavaScript errors on this page join the customer's activity trail (the shop sees them beside the chat) */
  var errs = 0, errSeen = {};
  function reportError(msg, src, line) {
    msg = String(msg || '').slice(0, 500); src = String(src || '');
    if (!msg || errs >= 5 || errSeen[msg] || /^(chrome|moz|safari)(-web)?-extension:/.test(src)) return;
    errSeen[msg] = 1; errs++;
    req('POST', '/assistant/client-log', { message: msg, source: src.slice(0, 300), line: line || null, url: location.pathname + location.search }).catch(function () {});
  }
  window.addEventListener('error', function (e) { if (e && e.message) reportError(e.message, e.filename, e.lineno); });
  window.addEventListener('unhandledrejection', function (e) { var r = e && e.reason; reportError('Unhandled promise: ' + String(r && r.message ? r.message : r).slice(0, 300)); });

  try { if (sessionStorage.getItem('ua.open') === '1') open(); } catch (e) {}
})();
