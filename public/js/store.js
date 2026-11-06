/* Universal Driveshafts storefront (2026 redesign). No dependencies.
   Uses the app's own endpoints: /api/search (header search), /api/catalog/driveshafts/step (vehicle finder) and
   /quick-order/add (add by SKU, same as the search dropdown). window.UDS_API points them at another host for the
   static preview; window.UDS_PREVIEW turns the cart calls into a preview notice. */
(function () {
  'use strict';
  var API = (window.UDS_API || '').replace(/\/$/, '');
  var PREVIEW = !!window.UDS_PREVIEW;
  var T = function (s, r) { return window.udsT ? window.udsT(s, r) : (r ? Object.keys(r).reduce(function (o, k) { return o.split(':' + k).join(r[k]); }, s) : s); };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  var money = function (n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var csrf = function () { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; };
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var abs = function (u) { return u && u.charAt(0) === '/' && API ? API + u : u; };

  /* ---------- ship-today countdown ---------- */
  function shipTick() {
    $$('[data-shipcd]').forEach(function (el) {
      var cut = Number(el.getAttribute('data-cut') || 0), left = cut - Date.now(), t = el.querySelector('[data-shipcd-t]');
      if (!t) return;
      if (cut && left > 0) {
        var m = Math.max(1, Math.ceil(left / 60000)), h = Math.floor(m / 60); m = m % 60;
        t.innerHTML = esc(el.getAttribute('data-today')).replace(':time', '<b>' + (h ? h + ' h ' : '') + m + ' min</b>');
        el.classList.add('is-today');
      } else { t.textContent = el.getAttribute('data-after'); el.classList.remove('is-today'); }
    });
  }
  shipTick(); setInterval(shipTick, 30000);

  /* ---------- sticky header shadow ---------- */
  var head = $('#st-head');
  if (head) {
    var onScroll = function () { head.classList.toggle('is-stuck', window.scrollY > 40); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* ---------- mega menu ---------- */
  var mega = $('[data-st-mega]');
  if (mega) {
    var mb = $('[data-st-mega-btn]', mega), mp = $('.st-mega__p', mega), timer;
    var setMega = function (o) { mp.hidden = !o; mb.setAttribute('aria-expanded', String(o)); };
    mb.addEventListener('click', function () { setMega(mp.hidden); });
    mega.addEventListener('mouseenter', function () { if (window.matchMedia('(hover: hover)').matches) { clearTimeout(timer); setMega(true); } });
    mega.addEventListener('mouseleave', function () { timer = setTimeout(function () { setMega(false); }, 160); });
    mp.addEventListener('mouseenter', function () { clearTimeout(timer); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMega(false); });
    document.addEventListener('click', function (e) { if (!mega.contains(e.target)) setMega(false); });
  }

  /* ---------- mobile menu ---------- */
  var menu = $('[data-st-menu]'), burger = $('[data-st-burger]');
  if (menu && burger) {
    var setMenu = function (o) {
      if (o) { menu.hidden = false; requestAnimationFrame(function () { menu.classList.add('is-open'); }); }
      else { menu.classList.remove('is-open'); setTimeout(function () { menu.hidden = true; }, 300); }
      burger.setAttribute('aria-expanded', String(o));
      document.documentElement.classList.toggle('st-lock', o);
      if (o) { var x = $('.st-menu__x', menu); if (x) x.focus(); } else burger.focus();
    };
    burger.addEventListener('click', function () { setMenu(true); });
    $$('[data-st-menu-close]', menu).forEach(function (b) { b.addEventListener('click', function () { setMenu(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.classList.contains('is-open')) setMenu(false); });
  }

  /* ---------- cart: header icon opens the drawer at every size ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-st-cart]');
    if (!a || e.metaKey || e.ctrlKey || PREVIEW) return;
    if (window.UDSCart) { e.preventDefault(); window.UDSCart.open(); }
  });

  /* ---------- floating contact ---------- */
  var fab = $('[data-st-fab]');
  if (fab) {
    var fb = $('.st-fab__btn', fab), fm = $('.st-fab__menu', fab);
    var setFab = function (o) { fm.hidden = !o; fb.setAttribute('aria-expanded', String(o)); };
    fb.addEventListener('click', function () { setFab(fm.hidden); });
    document.addEventListener('click', function (e) { if (!fab.contains(e.target)) setFab(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setFab(false); });
  }

  /* ---------- toast ---------- */
  var toastEl, toastT;
  function toast(html, action) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'st-toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
    toastEl.innerHTML = '<svg class="i" viewBox="0 0 256 256" fill="currentColor"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a11.31,11.31,0,0,1,0-16l0,0a8,8,0,0,1,11.32,0L96,183,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg><p>' + html + '</p>' + (action ? '<button type="button">' + esc(action.label) + '</button>' : '');
    if (action) toastEl.querySelector('button').onclick = function () { toastEl.classList.remove('is-on'); action.fn(); };
    requestAnimationFrame(function () { toastEl.classList.add('is-on'); });
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.classList.remove('is-on'); }, 4200);
  }

  /* ---------- add to cart by SKU (same endpoint as the search dropdown) ---------- */
  function addSku(sku, btn) {
    if (!sku) return;
    btn && btn.classList.add('is-busy');
    var done = function (count) {
      btn && (btn.classList.remove('is-busy'), btn.classList.add('is-done'));
      setTimeout(function () { btn && btn.classList.remove('is-done'); }, 1400);
      $$('[data-cart-count]').forEach(function (c) { c.textContent = count; c.hidden = !count; });
      toast(T('Added :sku to your cart.', { sku: '<b>' + esc(sku) + '</b>' }), PREVIEW ? null : { label: T('View cart'), fn: function () { window.UDSCart && window.UDSCart.open(); } });
    };
    if (PREVIEW) { var n = $('[data-cart-count]'); done((n && +n.textContent || 0) + 1); return; }
    fetch('/quick-order/add', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ lines: JSON.stringify([{ sku: sku, qty: 1 }]) }) })
      .then(function (r) { if (r.status === 419) throw new Error(T('Your session expired. Reload the page.')); return r.json().catch(function () { return {}; }); })
      .then(function (d) { if (!(d.added > 0)) throw new Error(d.error || T('Something went wrong. Try again.')); done(d.count || 0); window.UDSCart && window.UDSCart.refresh(); })
      .catch(function (err) { btn && btn.classList.remove('is-busy'); toast(esc(err.message)); });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add-sku]'); if (!b) return;
    e.preventDefault(); e.stopPropagation(); addSku(b.getAttribute('data-add-sku'), b);
  });

  /* ---------- header search: live, grouped results ---------- */
  var sf = $('[data-st-search]');
  if (sf) {
    var input = $('input', sf), box = $('[data-st-sres]', sf), seq = 0, deb, active = -1;
    var close = function () { box.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    var render = function (q, d) {
      var groups = (d && d.groups) || [];
      if (!groups.length) { box.innerHTML = '<p class="st-sres__none">' + T('No exact match for “:q”. Press Enter to search the whole catalog.', { q: esc(q) }) + '</p>'; }
      else {
        box.innerHTML = groups.slice(0, 4).map(function (g) {
          return '<div class="st-sres__g"><p class="st-sres__h"><span>' + esc(g.label) + ' · ' + (g.total || g.items.length) + '</span><a href="' + esc(abs(g.url)) + '">' + T('See all') + '</a></p>' +
            (g.items || []).slice(0, 3).map(function (p) {
              return '<a class="st-sres__it" role="option" href="' + esc(abs(p.url)) + '">' + (p.image ? '<img src="' + esc(abs(p.image)) + '" alt="" width="52" height="52" onerror="this.replaceWith(Object.assign(document.createElement(\'i\'),{className:\'st-sres__ph\'}))">' : '<i class="st-sres__ph"></i>') + '<span><b>' + esc(p.sku) + '</b><span>' + esc(p.name) + '</span></span>' +
                (Number(p.price) > 0 ? '<em>' + money(p.price) + '</em>' : '<em></em>') +
                (p.add && Number(p.price) > 0 ? '<button type="button" class="st-sres__add" data-add-sku="' + esc(p.add) + '" aria-label="' + esc(T('Add :sku to cart', { sku: p.sku })) + '"><svg class="i" viewBox="0 0 256 256" fill="currentColor"><path d="M228,128a12,12,0,0,1-12,12H140v76a12,12,0,0,1-24,0V140H40a12,12,0,0,1,0-24h76V40a12,12,0,0,1,24,0v76h76A12,12,0,0,1,228,128Z"/></svg></button>' : '<span></span>') + '</a>';
            }).join('') + '</div>';
        }).join('') + '<a class="st-sres__all" href="' + esc(abs('/search?q=' + encodeURIComponent(q))) + '">' + T('See all results for “:q”', { q: esc(q) }) + ' →</a>';
      }
      box.hidden = false; input.setAttribute('aria-expanded', 'true'); active = -1;
    };
    input.addEventListener('input', function () {
      var q = input.value.trim(); clearTimeout(deb);
      if (q.length < 2) { close(); return; }
      deb = setTimeout(function () {
        var my = ++seq;
        fetch(API + '/api/search?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (d) { if (my === seq && input.value.trim() === q) render(q, d); }).catch(function () {});
      }, 170);
    });
    input.addEventListener('keydown', function (e) {
      var items = $$('.st-sres__it', box); if (box.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (it, i) { it.classList.toggle('is-active', i === active); }); items[active].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); location.href = items[active].href; }
      else if (e.key === 'Escape') close();
    });
    input.addEventListener('focus', function () { if (box.innerHTML && input.value.trim().length > 1) { box.hidden = false; } });
    document.addEventListener('click', function (e) { if (!sf.contains(e.target)) close(); });
    if (API) sf.setAttribute('action', API + '/search');
  }

  /* ---------- hero console: tabs ---------- */
  var con = $('[data-console]');
  if (con) {
    var tabs = $$('[data-con-tab]', con);
    var pick = function (t, focus) {
      tabs.forEach(function (x) {
        var on = x === t; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1;
        document.getElementById(x.getAttribute('aria-controls')).hidden = !on;
      });
      if (focus) t.focus();
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { pick(t); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); pick(tabs[(i + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length], true); }
      });
    });
    if (API) $$('form[action^="/"]', con).forEach(function (f) { f.setAttribute('action', API + f.getAttribute('action')); });
  }

  /* ---------- vehicle finder (the finder's own step API, so only real combinations show) ---------- */
  var fin = $('[data-finder]');
  if (fin) {
    var keys = ['make', 'year', 'model'], sel = {}, chosen = {};
    keys.forEach(function (k) { sel[k] = $('[data-f="' + k + '"]', fin); });
    var wrap = function (k) { return sel[k].closest('.h-sel'); };
    var mark = function () { keys.forEach(function (k) { wrap(k).classList.toggle('is-set', !!sel[k].value); wrap(k).classList.remove('is-next'); }); };
    var reset = function (from) { keys.slice(from).forEach(function (k) { sel[k].innerHTML = '<option value="">' + esc(T(k.charAt(0).toUpperCase() + k.slice(1))) + '</option>'; sel[k].disabled = true; delete chosen[k]; }); };
    var fill = function (k, label, opts) {
      sel[k].innerHTML = '<option value="">' + esc(T('Choose :what (:n)', { what: T(label).toLowerCase(), n: opts.length })) + '</option>' + opts.map(function (o) { return '<option>' + esc(o) + '</option>'; }).join('');
      sel[k].disabled = false; mark(); if (k !== 'make') wrap(k).classList.add('is-next');
    };
    var step = function () {
      var q = new URLSearchParams(chosen).toString();
      return fetch(API + '/api/catalog/driveshafts/step' + (q ? '?' + q : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
    };
    var next = function (then) { step().then(function (d) { var n = d && d.next; if (n && sel[n.facet]) fill(n.facet, n.label, n.options || []); then && then(); }).catch(function () {}); };
    keys.forEach(function (k, i) {
      sel[k].addEventListener('change', function () {
        $$('.h-con__makes button', fin).forEach(function (b) { b.classList.toggle('is-on', k === 'make' && b.getAttribute('data-make') === sel.make.value); });
        if (!sel[k].value) { reset(i); mark(); if (i === 0) next(); return; }
        chosen[k] = sel[k].value; reset(i + 1); mark(); next();
      });
    });
    $$('.h-con__makes button', fin).forEach(function (b) {
      b.addEventListener('click', function () {
        var mk = b.getAttribute('data-make');
        var opt = Array.prototype.find.call(sel.make.options, function (o) { return o.value.toLowerCase() === mk.toLowerCase(); });
        if (!opt) return; sel.make.value = opt.value; sel.make.dispatchEvent(new Event('change'));
      });
    });
    fin.addEventListener('submit', function (e) {
      if (!sel.make.value) { e.preventDefault(); wrap('make').classList.add('is-next'); sel.make.focus(); }
    });
    next();
  }

  /* ---------- product rail arrows ---------- */
  var rail = $('[data-rail]');
  if (rail) {
    var by = function (dir) { var c = rail.querySelector('.h-prod'); rail.scrollBy({ left: dir * (c ? c.getBoundingClientRect().width + 14 : 300) * 2, behavior: reduced ? 'auto' : 'smooth' }); };
    var p = $('[data-rail-prev]'), n = $('[data-rail-next]');
    p && p.addEventListener('click', function () { by(-1); }); n && n.addEventListener('click', function () { by(1); });
  }

  /* ---------- u-joint identifier (same rules as PartIdentifier::inches / matchUJoint) ---------- */
  var uid = $('[data-uid]');
  if (uid) {
    var SER = JSON.parse(uid.getAttribute('data-series') || '[]');
    var capIn = $('[data-uid-cap]', uid), wIn = $('[data-uid-w]', uid), out = $('[data-uid-out]', uid);
    var cards = $$('[data-uid-grid] .h-ser'), hint = out.innerHTML, ut;
    var inches = function (v) {
      v = String(v || '').trim(); if (!v) return null;
      var m = v.match(/^(\d+)\s+(\d+)\s*\/\s*(\d+)/); if (m) return +m[1] + m[2] / Math.max(1, m[3]);
      m = v.match(/^(\d+)\s*\/\s*(\d+)/); if (m) return m[1] / Math.max(1, m[2]);
      m = v.match(/(\d+(?:[.,]\d+)?)/); if (!m) return null;
      var n = parseFloat(m[1].replace(',', '.'));
      return (/mm|millim/i.test(v) || n > 10) ? n / 25.4 : n;   // nobody has a 27-inch cap
    };
    var orList = function (a) { return a.length < 2 ? a.join('') : a.slice(0, -1).join(', ') + ' ' + T('or') + ' ' + a[a.length - 1]; };
    var link = function (s) { return '<a href="' + esc(abs('/series/' + encodeURIComponent(s.s))) + '">' + T('Shop :s', { s: s.s }) + ' &rarr;</a>'; };
    var spec = function (s) { return s.cap.toFixed(3) + ' × ' + s.w.toFixed(3) + ', ' + (s.ring === 'inside' ? T('inside snap ring') : T('outside snap ring')); };
    var run = function () {
      var c = inches(capIn.value), w = inches(wIn.value), state = '', html = hint, hits = [];
      if (c || w) {
        var sc = SER.map(function (s) {
          var dc = c ? Math.abs(c - s.cap) : null, dw = w ? Math.abs(w - s.w) : null;
          return { s: s, ok: dc === null || dc <= 0.010, wok: dw === null || dw <= 0.10, d: (dc || 0) + (dw === null ? 0.5 : dw) };
        }).filter(function (x) { return x.ok; }).sort(function (a, b) { return a.d - b.d; });
        if (!c) { sc = sc.filter(function (x) { return x.wok; }); }
        var best = sc[0];
        if (best && c && w && best.wok) {
          state = 'ok'; hits = [best.s.s];
          html = '<b>' + T('That is a :s.', { s: best.s.s }) + '</b> <span>' + spec(best.s) + '</span> ' + link(best.s);
        } else if (best && (!w || !c)) {
          state = 'some'; hits = sc.map(function (x) { return x.s.s; });
          html = (c ? T('That cap fits :list. Add the width to tell them apart.', { list: orList(hits) }) : T('That width is close to :list. Add the cap diameter.', { list: orList(hits) }));
        } else if (best) {
          state = 'warn'; hits = [best.s.s];
          html = T('The cap fits :s, but no series with that cap is that wide. Measure lock ring to lock ring, or across the outer edges of the snap ring grooves.', { s: best.s.s });
        } else {
          state = 'warn';
          html = T('No standard series uses that cap. Common caps are 1.062, 1.078, 1.125, 1.188 and 1.375 in.') + ' <a href="' + uid.getAttribute('data-wa') + '" target="_blank" rel="noopener">' + T('Send a photo to a tech') + ' &rarr;</a>';
        }
      }
      uid.setAttribute('data-state', state);
      out.innerHTML = html;
      cards.forEach(function (a) {
        var s = (a.getAttribute('href') || '').split('/').pop();
        a.classList.toggle('is-hit', hits.indexOf(s) > -1);
        a.classList.toggle('is-dim', hits.length > 0 && hits.indexOf(s) < 0);
      });
    };
    [capIn, wIn].forEach(function (i) { i.addEventListener('input', function () { clearTimeout(ut); ut = setTimeout(run, 140); }); });
  }

  /* ---------- reveal ---------- */
  var rv = $$('[data-rv]');
  if (rv.length) {
    if (reduced || !('IntersectionObserver' in window)) rv.forEach(function (el) { el.classList.add('in'); });
    else {
      var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -8% 0px' });
      rv.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
      setTimeout(function () { rv.forEach(function (el) { el.classList.add('in'); }); }, 2500);
    }
  }
})();
