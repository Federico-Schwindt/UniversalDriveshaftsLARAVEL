/* Slide-in cart panel. Opens from the header cart icon on phones; after any add-to-cart, whichever page
   script made the request (it watches fetch for the cart "add" endpoints), it refreshes the count. */
(function () {
  var root = document.getElementById('ucart'); if (!root) return;
  var T = window.UCART_T || {}, CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var body = root.querySelector('[data-ucart-body]'), foot = root.querySelector('[data-ucart-foot]');
  var PHONE = window.matchMedia('(max-width: 1240px)');
  var ADD_URL = /\/(cart\/add|shop\/catalog\/add|catalog\/driveshafts\/add)(\?|$)/;
  var lastFocus = null;

  function money(n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function badges(count) {
    document.querySelectorAll('[data-cart-count]').forEach(function (b) { b.textContent = count; b.hidden = !count; });
  }

  function render(d) {
    var items = d.items || [], count = d.count || 0;
    badges(count);
    var c = root.querySelector('[data-ucart-count]');
    c.textContent = count === 1 ? T.item : (T.items || ':n').replace(':n', count); c.hidden = !count;
    ship(d, items.length);
    if (!items.length) {
      body.innerHTML = '<div class="ucart__empty"><span class="ucart__empty-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.5L21 8H6"/></svg></span>' +
        '<b>' + esc(T.empty) + '</b><p>' + esc(T.emptySub) + '</p><a href="/shop" class="btn btn-primary">' + esc(T.browse) + '</a></div>';
      foot.hidden = true; return;
    }
    body.innerHTML = '<ul class="ucart__list">' + items.map(function (it) {
      var pic = it.image ? '<img src="' + esc(it.image) + '" alt="" loading="lazy">' : '<span class="ucart__ph" aria-hidden="true"></span>';
      var each = it.qty > 1 ? '<small>' + esc((T.each || ':price').replace(':price', money(it.price))) + '</small>' : '';
      return '<li class="ucart__it" data-key="' + esc(it.key) + '"><span class="ucart__pic">' + pic + '</span>' +
        '<div class="ucart__info">' + (it.sku ? '<span class="ucart__sku">' + esc(it.sku) + '</span>' : '') + '<b>' + esc(it.name) + '</b>' +
        '<div class="ucart__row"><div class="ucart__qty" role="group" aria-label="' + esc(T.qty) + '">' +
        '<button type="button" data-q="-1" aria-label="' + esc(T.less) + '">\u2212</button><span aria-live="polite">' + it.qty + '</span><button type="button" data-q="1" aria-label="' + esc(T.more) + '">+</button></div>' +
        '<span class="ucart__price">' + money(it.price * it.qty) + each + '</span></div></div>' +
        '<button type="button" class="ucart__rm" data-rm aria-label="' + esc((T.remove || ':name').replace(':name', it.sku || it.name)) + '"><svg width="18" height="18" aria-hidden="true"><use href="#ucart-trash"/></svg></button></li>';
    }).join('') + '</ul>';
    root.querySelector('[data-ucart-subtotal]').textContent = money(d.subtotal);
    foot.hidden = false;
  }

  // free US shipping meter (parts only; tubing and complete driveshafts are rated)
  function ship(d, n) {
    var box = root.querySelector('[data-ucart-ship]'), min = +d.free_min || 0, base = +d.free_base || 0;
    if (!n || !min) { box.hidden = true; return; }
    var pct = Math.max(0, Math.min(100, Math.round(base / min * 100))), done = base >= min;
    box.hidden = false; box.classList.toggle('is-done', done);
    root.querySelector('[data-ucart-ship-txt]').innerHTML = done ? esc(T.shipOk) : esc(T.shipLeft).replace(':amount', '<b>' + money(min - base) + '</b>');
    root.querySelector('[data-ucart-bar]').style.width = pct + '%';
    root.querySelector('.ucart__bar').setAttribute('aria-valuenow', pct);
  }

  function load() {
    return fetch('/cart/mini', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (j) { if (j && j.success) render(j.data); return j; });
  }

  function open() {
    lastFocus = document.activeElement;
    root.hidden = false; root.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { root.classList.add('is-open'); });
    document.documentElement.classList.add('ucart-lock');
    var x = root.querySelector('.ucart__x'); if (x) x.focus({ preventScroll: true });
  }
  function close() {
    root.classList.remove('is-open'); root.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('ucart-lock');
    setTimeout(function () { if (!root.classList.contains('is-open')) root.hidden = true; }, 260);
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }

  function post(url, data) {
    root.classList.add('is-busy');
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data) })
      .then(function (r) { return r.json(); }).then(function (j) { root.classList.remove('is-busy'); if (j && j.success) render(j.data); })
      .catch(function () { root.classList.remove('is-busy'); });
  }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-ucart-close]')) { close(); return; }
    var li = e.target.closest('.ucart__it'); if (!li) return;
    var key = li.getAttribute('data-key');
    var q = e.target.closest('[data-q]');
    if (q) { var n = parseInt(li.querySelector('.ucart__qty span').textContent, 10) + parseInt(q.getAttribute('data-q'), 10);
      post(n > 0 ? '/cart/update' : '/cart/remove', n > 0 ? { key: key, qty: n } : { key: key }); return; }
    if (e.target.closest('[data-rm]')) post('/cart/remove', { key: key });
  });
  // aria-modal: Tab stays inside the open panel (wraps at both ends) until it is closed
  document.addEventListener('keydown', function (e) {
    if (!root.classList.contains('is-open')) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key !== 'Tab') return;
    var f = Array.prototype.filter.call(root.querySelectorAll('.ucart__panel a[href], .ucart__panel button:not([disabled]), .ucart__panel input:not([disabled]), .ucart__panel [tabindex]:not([tabindex="-1"])'),
      function (el) { return el.offsetParent !== null && !el.closest('[hidden]'); });
    if (!f.length) { e.preventDefault(); return; }
    var first = f[0], last = f[f.length - 1], a = document.activeElement;
    if (!root.contains(a)) { e.preventDefault(); first.focus(); }
    else if (e.shiftKey && a === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && a === last) { e.preventDefault(); first.focus(); }
  });

  // header cart icon: on phones and tablets it opens the panel instead of leaving the page
  document.addEventListener('click', function (e) {
    var a = e.target.closest('.iconbtn--cart, [data-open-cart]');
    if (!a || !PHONE.matches || e.metaKey || e.ctrlKey) return;
    e.preventDefault(); var m = document.querySelector('#udrawer [data-drawer-close]'); if (a.hasAttribute('data-open-cart') && m) m.click(); load(); open();
  });

  // any successful add-to-cart, from any page script: refresh and (on phones) slide the panel in
  if (window.fetch) {
    var orig = window.fetch;
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var p = orig.apply(this, arguments);
      if (ADD_URL.test(url) && (!init || (init.method || 'GET').toUpperCase() === 'POST')) {
        p.then(function (r) { return r.clone().json(); }).then(function (j) {
          if (!j || !j.success) return;
          load();   // owner, 2026-10-24: refresh the count only; the panel opens from the cart icon
        }).catch(function () {});
      }
      return p;
    };
  }
  window.UDSCart = { open: function () { load(); open(); }, refresh: load };
})();
