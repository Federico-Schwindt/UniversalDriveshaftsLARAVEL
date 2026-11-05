/* UDS Admin v2 — shell behaviours. No framework; everything hangs off data-attributes. */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var csrf = ($('meta[name=csrf-token]') || {}).content || '';
  var adm = $('#adm');

  /* ---------- toasts ---------- */
  function toast(msg, kind) {
    var box = $('#toasts'); if (!box) return;
    var el = document.createElement('div');
    el.className = 'toast' + (kind ? ' toast--' + kind : '');
    el.innerHTML = '<svg class="ic"><use href="#i-' + (kind === 'err' ? 'alert' : 'check') + '"/></svg><span></span>';
    el.querySelector('span').textContent = msg;
    box.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(function () { el.remove(); }, 320); }, 3800);
  }
  window.admToast = toast;
  $$('[data-toast]').forEach(function (f) { toast(f.textContent.trim(), f.classList.contains('flash--err') ? 'err' : 'ok'); f.remove(); });

  /* ---------- drawer / sidebar ---------- */
  var scrim = $('.adm-scrim');
  function openDrawer() { adm.classList.add('drawer-open'); if (scrim) scrim.hidden = false; document.body.style.overflow = 'hidden'; }
  function closeDrawer() { adm.classList.remove('drawer-open'); if (scrim) scrim.hidden = true; document.body.style.overflow = ''; }
  $$('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', openDrawer); });
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); closeMenus(); closeModal(); } });
  try { if (localStorage.getItem('adm.side') === 'c') adm.classList.add('side-collapsed'); } catch (e) {}
  var col = $('[data-collapse]');
  if (col) col.addEventListener('click', function () {
    adm.classList.toggle('side-collapsed');
    try { localStorage.setItem('adm.side', adm.classList.contains('side-collapsed') ? 'c' : 'o'); } catch (e) {}
  });

  /* ---------- dropdown menus ---------- */
  // a right-aligned menu whose button sits at the left edge (page actions on a phone) would open off-screen: nudge it back in
  function fitMenu(pop) {
    pop.style.left = ''; pop.style.right = '';
    var r = pop.getBoundingClientRect(), vw = document.documentElement.clientWidth, pad = 8;
    if (r.left < pad) { pop.style.left = '0'; pop.style.right = 'auto'; r = pop.getBoundingClientRect(); }
    if (r.right > vw - pad) { pop.style.left = 'auto'; pop.style.right = '0'; }
  }
  function closeMenus(except) { $$('.adm-menu__pop').forEach(function (p) { if (p !== except) p.hidden = true; }); }
  $$('[data-menu]').forEach(function (m) {
    var btn = $('[data-menu-btn]', m), pop = $('.adm-menu__pop', m);
    if (!btn || !pop) return;
    btn.addEventListener('click', function (e) { e.stopPropagation(); var was = pop.hidden; closeMenus(); pop.hidden = !was; if (!pop.hidden) fitMenu(pop); });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('[data-menu]')) closeMenus(); });

  /* ---------- confirm + POST links ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset && f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }
    var btn = f.querySelector('[type=submit]'); if (btn && !f.hasAttribute('data-no-lock')) setTimeout(function () { btn.classList.add('is-loading'); }, 0);
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-post]'); if (!a) return;
    e.preventDefault();
    if (a.dataset.confirm && !window.confirm(a.dataset.confirm)) return;
    var f = document.createElement('form'); f.method = 'POST'; f.action = a.dataset.post; f.style.display = 'none';
    f.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">';
    try { var extra = JSON.parse(a.dataset.fields || '{}'); Object.keys(extra).forEach(function (k) { var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = extra[k]; f.appendChild(i); }); } catch (err) {}
    document.body.appendChild(f); f.submit();
  });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]'); if (!c) return;
    e.preventDefault();
    navigator.clipboard && navigator.clipboard.writeText(c.dataset.copy).then(function () { toast('Copied: ' + c.dataset.copy, 'ok'); });
  });

  /* ---------- tables: row links, select-all, bulk bar ---------- */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-href]'); if (!tr) return;
    if (e.target.closest('a,button,input,select,textarea,label,[data-stop]')) return;
    if (e.metaKey || e.ctrlKey) window.open(tr.dataset.href, '_blank'); else window.location = tr.dataset.href;
  });
  $$('[data-bulk]').forEach(function (wrap) {
    var all = $('[data-check-all]', wrap), boxes = $$('[data-check]', wrap), bar = $('[data-bulkbar]', wrap), cnt = $('[data-bulk-count]', wrap), ids = $('[data-bulk-ids]', wrap);
    function sync() {
      var on = boxes.filter(function (b) { return b.checked; });
      if (bar) bar.classList.toggle('is-on', on.length > 0);
      if (cnt) cnt.textContent = on.length;
      if (ids) ids.value = on.map(function (b) { return b.value; }).join(',');
      if (all) { all.indeterminate = on.length > 0 && on.length < boxes.length; all.checked = on.length === boxes.length && boxes.length > 0; }
    }
    if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    // bulk actions touch many rows at once: no default action, and say how many before applying
    if (wrap.tagName === 'FORM') wrap.addEventListener('submit', function (e) {
      var sel = $('select[name="bulk"]', wrap); if (!sel) return;
      var n = boxes.filter(function (b) { return b.checked; }).length, opt = sel.options[sel.selectedIndex];
      if (!sel.value) { e.preventDefault(); toast('Choose an action first.', 'err'); return; }
      if (sel.value === 'print') return;
      var msg = (opt.textContent || '').replace(/…$/, '') + ' — ' + n + ' selected row' + (n === 1 ? '' : 's') + '?' + (opt.dataset.warn ? '\n\n' + opt.dataset.warn : '');
      if (!window.confirm(msg)) e.preventDefault();
    });
  });

  /* ---------- tabs ---------- */
  $$('[data-tabs]').forEach(function (t) {
    var links = $$('[data-tab]', t), panels = $$('[data-tabpanel]');
    function show(id, push) {
      links.forEach(function (l) { l.classList.toggle('is-on', l.dataset.tab === id); });
      panels.forEach(function (p) { p.hidden = p.dataset.tabpanel !== id; });
      if (push) try { history.replaceState(null, '', '#' + id); } catch (e) {}
    }
    links.forEach(function (l) { l.addEventListener('click', function (e) { e.preventDefault(); show(l.dataset.tab, true); }); });
    var h = location.hash.replace('#', '');
    if (h && links.some(function (l) { return l.dataset.tab === h; })) show(h); else if (links[0]) show(links[0].dataset.tab);
  });

  /* ---------- dirty forms → sticky save bar ---------- */
  $$('form[data-savebar]').forEach(function (f) {
    var bar = $(f.dataset.savebar); if (!bar) return;
    var snap = new FormData(f), base = [];
    snap.forEach(function (v, k) { base.push(k + '=' + v); });
    base = base.join('&');
    function check() { var cur = []; new FormData(f).forEach(function (v, k) { cur.push(k + '=' + v); }); bar.classList.toggle('is-on', cur.join('&') !== base || f.dataset.forceDirty === '1'); }
    f.addEventListener('input', check); f.addEventListener('change', check);
    f.addEventListener('dirty', function () { f.dataset.forceDirty = '1'; check(); });
    window.addEventListener('beforeunload', function (e) { if (bar.classList.contains('is-on') && !f.dataset.submitting) { e.preventDefault(); e.returnValue = ''; } });
    f.addEventListener('submit', function () { f.dataset.submitting = '1'; });
  });

  /* ---------- modal ---------- */
  var modalEl = null;
  function openModal(html, opts) {
    closeModal();
    opts = opts || {};
    modalEl = document.createElement('div');
    modalEl.className = 'modal' + (opts.xl ? ' modal--xl' : opts.wide ? ' modal--wide' : '');
    modalEl.innerHTML = '<div class="modal__box" role="dialog" aria-modal="true">' + html + '</div>';
    modalEl.addEventListener('click', function (e) { if (e.target === modalEl) closeModal(); });
    document.body.appendChild(modalEl);
    $$('[data-modal-close]', modalEl).forEach(function (b) { b.addEventListener('click', closeModal); });
    var first = $('input,select,textarea,button', modalEl); if (first) setTimeout(function () { first.focus(); }, 50);
    return modalEl;
  }
  function closeModal() { if (modalEl) { modalEl.remove(); modalEl = null; } }
  window.admModal = openModal; window.admModalClose = closeModal;
  $$('template[data-modal-src]').forEach(function (tpl) {
    $$('[data-modal="' + tpl.dataset.modalSrc + '"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) { e.preventDefault(); openModal(tpl.innerHTML, { wide: tpl.hasAttribute('data-wide'), xl: tpl.hasAttribute('data-xl') }); });
    });
  });

  /* ---------- global search ---------- */
  var sIn = $('[data-search-input]'), sPop = $('[data-search-pop]'), sT, sAct = -1;
  if (sIn && sPop) {
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && !e.target.closest('input,textarea,select,[contenteditable]')) { e.preventDefault(); sIn.focus(); sIn.select(); }
    });
    function render(data, q) {
      var html = '', n = 0;
      (data.groups || []).forEach(function (g) {
        if (!g.items.length) return;
        html += '<div class="adm-search__grp">' + esc(g.label) + '</div>';
        g.items.forEach(function (it) {
          n++;
          html += '<a class="adm-search__row" href="' + esc(it.url) + '">' + (it.img ? '<img src="' + esc(it.img) + '" alt="">' : '<svg class="ic"><use href="#i-' + esc(g.icon || 'search') + '"/></svg>') + '<span><b>' + esc(it.title) + '</b>' + (it.sub ? ' <span class="muted">' + esc(it.sub) + '</span>' : '') + '</span>' + (it.meta ? '<small>' + esc(it.meta) + '</small>' : '') + '</a>';
        });
      });
      if (!n) html = '<div class="adm-search__empty">Nothing matches “' + esc(q) + '”. Press Enter for full search.</div>';
      sPop.innerHTML = html; sPop.hidden = false; sAct = -1;
    }
    sIn.addEventListener('input', function () {
      var q = sIn.value.trim(); clearTimeout(sT);
      if (q.length < 2) { sPop.hidden = true; return; }
      sT = setTimeout(function () {
        fetch('/admin/api/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { if (sIn.value.trim() === q) render(d, q); }).catch(function () {});
      }, 160);
    });
    sIn.addEventListener('keydown', function (e) {
      var rows = $$('.adm-search__row', sPop);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (sPop.hidden || !rows.length) return; e.preventDefault();
        sAct = (sAct + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
        rows.forEach(function (r, i) { r.classList.toggle('is-active', i === sAct); });
        rows[sAct].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (sAct >= 0 && rows[sAct]) window.location = rows[sAct].href; else if (sIn.value.trim()) window.location = '/admin/search?q=' + encodeURIComponent(sIn.value.trim());
      } else if (e.key === 'Escape') { sPop.hidden = true; sIn.blur(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('#adm-search')) sPop.hidden = true; });
    sIn.addEventListener('focus', function () { if (sPop.innerHTML && sIn.value.trim().length >= 2) sPop.hidden = false; });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  window.admEsc = esc;

  /* ---------- combobox: remote search picker ----------
     admCombo(input, { url: '/admin/api/products?q=', render(item) -> html, onPick(item) }) */
  window.admCombo = function (input, opts) {
    var wrap = input.closest('.combo') || input.parentNode, pop = document.createElement('div'), t, act = -1, items = [];
    pop.className = 'combo__pop'; pop.hidden = true; wrap.appendChild(pop);
    function draw() {
      if (!items.length) { pop.innerHTML = '<div class="combo__empty">' + (opts.empty || 'No matches') + '</div>'; pop.hidden = false; return; }
      pop.innerHTML = items.map(function (it, i) { return '<div class="combo__row" data-i="' + i + '">' + opts.render(it) + '</div>'; }).join('');
      pop.hidden = false; act = -1;
      $$('.combo__row', pop).forEach(function (r) { r.addEventListener('mousedown', function (e) { e.preventDefault(); pick(+r.dataset.i); }); });
    }
    function pick(i) { var it = items[i]; if (!it) return; pop.hidden = true; opts.onPick(it, input); if (opts.clear !== false) input.value = ''; }
    input.addEventListener('input', function () {
      var q = input.value.trim(); clearTimeout(t);
      if (q.length < (opts.min || 2)) { pop.hidden = true; return; }
      t = setTimeout(function () {
        fetch(opts.url + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { if (input.value.trim() !== q) return; items = d.items || d; draw(); }).catch(function () {});
      }, 150);
    });
    input.addEventListener('keydown', function (e) {
      var rows = $$('.combo__row', pop);
      if (pop.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); act = (act + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length; rows.forEach(function (r, i) { r.classList.toggle('is-active', i === act); }); }
      else if (e.key === 'Enter') { if (act >= 0) { e.preventDefault(); pick(act); } }
      else if (e.key === 'Escape') pop.hidden = true;
    });
    input.addEventListener('blur', function () { setTimeout(function () { pop.hidden = true; }, 120); });
  };

  /* ---------- image uploader (drag/drop + reorder) ----------
     <div data-uploader data-url="/admin/media/upload" data-name="images[]"> <div class="media-grid" data-grid></div> <label class="dropzone">…<input type=file multiple></label> </div> */
  $$('[data-uploader]').forEach(function (up) {
    var grid = $('[data-grid]', up), file = $('input[type=file]', up), zone = $('.dropzone', up), name = up.dataset.name || 'images[]', form = up.closest('form');
    function addItem(url) {
      var d = document.createElement('div'); d.className = 'media-item'; d.draggable = true;
      d.innerHTML = '<img src="' + esc(url) + '" alt=""><input type="hidden" name="' + esc(name) + '" value="' + esc(url) + '"><button type="button" class="rm" aria-label="Remove"><svg class="ic ic--s"><use href="#i-x"/></svg></button>';
      grid.appendChild(d); mark(); if (form) form.dispatchEvent(new Event('dirty'));
    }
    function mark() { $$('.media-item', grid).forEach(function (m, i) { m.classList.toggle('is-main', i === 0); }); }
    grid.addEventListener('click', function (e) { var b = e.target.closest('.rm'); if (b) { b.closest('.media-item').remove(); mark(); if (form) form.dispatchEvent(new Event('dirty')); } });
    var drag = null;
    grid.addEventListener('dragstart', function (e) { drag = e.target.closest('.media-item'); if (drag) drag.classList.add('dragging'); });
    grid.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); drag = null; mark(); if (form) form.dispatchEvent(new Event('dirty')); });
    grid.addEventListener('dragover', function (e) { e.preventDefault(); var over = e.target.closest('.media-item'); if (!over || over === drag) return; var r = over.getBoundingClientRect(); grid.insertBefore(drag, (e.clientX - r.left) > r.width / 2 ? over.nextSibling : over); });
    function send(files) {
      Array.prototype.forEach.call(files, function (f) {
        if (!/^image\//.test(f.type)) { toast('Only images are accepted', 'err'); return; }
        var fd = new FormData(); fd.append('file', f); fd.append('_token', csrf);
        zone.classList.add('is-over');
        fetch(up.dataset.url, { method: 'POST', body: fd, headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
          zone.classList.remove('is-over');
          if (d.url) addItem(d.url); else toast(d.error || 'Upload failed', 'err');
        }).catch(function () { zone.classList.remove('is-over'); toast('Upload failed', 'err'); });
      });
    }
    if (file) file.addEventListener('change', function () { send(file.files); file.value = ''; });
    if (zone) {
      zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('is-over'); });
      zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
      zone.addEventListener('drop', function (e) { e.preventDefault(); zone.classList.remove('is-over'); send(e.dataTransfer.files); });
    }
    mark();
  });

  /* ---------- tag inputs ---------- */
  $$('[data-tags]').forEach(function (box) {
    var inp = $('input[type=text]', box), hid = $('input[type=hidden]', box);
    function vals() { return $$('.tag', box).map(function (t) { return t.dataset.v; }); }
    function sync() { hid.value = vals().join(','); }
    function add(v) { v = v.trim(); if (!v || vals().indexOf(v) >= 0) return; var t = document.createElement('span'); t.className = 'tag'; t.dataset.v = v; t.innerHTML = esc(v) + '<button type="button" aria-label="Remove"><svg class="ic ic--s"><use href="#i-x"/></svg></button>'; box.insertBefore(t, inp); sync(); }
    (hid.value || '').split(',').forEach(add);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(inp.value); inp.value = ''; } else if (e.key === 'Backspace' && !inp.value) { var last = $$('.tag', box).pop(); if (last) { last.remove(); sync(); } } });
    inp.addEventListener('blur', function () { if (inp.value.trim()) { add(inp.value); inp.value = ''; } });
    box.addEventListener('click', function (e) { var b = e.target.closest('.tag button'); if (b) { b.parentNode.remove(); sync(); } else inp.focus(); });
  });

  /* ---------- charts (inline SVG) ----------
     <svg class="chart" data-chart='{"type":"line","labels":[],"series":[{"name":"Sales","data":[]}],"money":true}'> */
  function chart(svg) {
    var cfg; try { cfg = JSON.parse(svg.dataset.chart); } catch (e) { return; }
    // drawn at the width it is shown at, so text stays 11px instead of shrinking with the SVG on a phone
    var W = Math.max(280, Math.round(svg.getBoundingClientRect().width || svg.parentNode.clientWidth || 640));
    var H = cfg.height || 200, padL = 44, padR = 10, padT = 14, padB = 26, labels = cfg.labels || [], series = cfg.series || [];
    var max = 0; series.forEach(function (s) { s.data.forEach(function (v) { if (v > max) max = v; }); });
    var empty = !max;
    if (!max) max = 1;
    var nice = Math.pow(10, Math.floor(Math.log10(max))); var step = nice; while (max / step > 5) step *= 2; var top = Math.ceil(max / step) * step;
    var iw = W - padL - padR, ih = H - padT - padB, n = Math.max(labels.length, 1);
    var fmt = function (v) { return cfg.money ? '$' + (v >= 1000 ? (v / 1000).toFixed(v >= 10000 ? 0 : 1) + 'k' : Math.round(v)) : (v >= 1000 ? (v / 1000).toFixed(1) + 'k' : Math.round(v)); };
    var out = '<defs><linearGradient id="chartfill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1246ab" stop-opacity=".22"/><stop offset="1" stop-color="#1246ab" stop-opacity="0"/></linearGradient></defs>';
    for (var g = 0; g <= top; g += step) { var y = padT + ih - (g / top) * ih; out += '<line class="grid-l" x1="' + padL + '" x2="' + (W - padR) + '" y1="' + y + '" y2="' + y + '"/><text x="' + (padL - 6) + '" y="' + (y + 4) + '" text-anchor="end">' + fmt(g) + '</text>'; }
    var every = Math.ceil(n / (cfg.maxLabels || Math.max(3, Math.min(8, Math.floor(W / 80)))));
    labels.forEach(function (l, i) { if (i % every) return; var x = padL + (n === 1 ? iw / 2 : (i / (n - 1)) * iw); if (cfg.type === 'bar') x = padL + (i + .5) * (iw / n); out += '<text x="' + x + '" y="' + (H - 8) + '" text-anchor="middle">' + esc(l) + '</text>'; });
    if (empty) {
      out += '<text class="chart-empty" x="' + (padL + iw / 2) + '" y="' + (padT + ih / 2) + '" text-anchor="middle">No sales in this period</text>';
      series = [];
    }
    series.forEach(function (s, si) {
      if (cfg.type === 'bar') {
        var bw = (iw / n) * (series.length > 1 ? .36 : .62);
        // a day with more refunds than sales nets below zero: drawn as an empty bar, never a negative one
        s.data.forEach(function (v, i) { var h = Math.max(0, (v / top) * ih), x = padL + (i + .5) * (iw / n) - (series.length > 1 ? bw * (series.length / 2 - si) : bw / 2); out += '<rect class="bar' + (si ? ' bar--alt' : '') + '" x="' + x + '" y="' + (padT + ih - h) + '" width="' + bw + '" height="' + h + '" rx="3"><title>' + esc(labels[i] || '') + ': ' + (cfg.money ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : v) + '</title></rect>'; });
      } else {
        var pts = s.data.map(function (v, i) { return [padL + (n === 1 ? iw / 2 : (i / (n - 1)) * iw), padT + ih - (v / top) * ih]; });
        var d = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');
        if (!si) out += '<path class="area" d="' + d + ' L' + pts[pts.length - 1][0].toFixed(1) + ' ' + (padT + ih) + ' L' + padL + ' ' + (padT + ih) + 'Z"/>';
        out += '<path class="line" ' + (si ? 'style="stroke:#b7cdf0"' : '') + ' d="' + d + '"/>';
        pts.forEach(function (p, i) { out += '<circle cx="' + p[0].toFixed(1) + '" cy="' + p[1].toFixed(1) + '" r="3.2" fill="#fff" stroke="' + (si ? '#b7cdf0' : '#1246ab') + '" stroke-width="2"><title>' + esc(labels[i] || '') + ': ' + (cfg.money ? '$' + Number(s.data[i]).toLocaleString(undefined, { minimumFractionDigits: 2 }) : s.data[i]) + '</title></circle>'; });
      }
    });
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.innerHTML = out;
  }
  $$('svg[data-chart]').forEach(chart);
  var chartT; window.addEventListener('resize', function () { clearTimeout(chartT); chartT = setTimeout(function () { $$('svg[data-chart]').forEach(chart); }, 150); });
  window.admChart = chart;

  /* ---------- auto-submit filters ---------- */
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form && el.form.submit(); }); });

  /* ---------- fetch helper for inline JSON posts ---------- */
  window.admPost = function (url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(data || {}) }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); });
  };
})();
