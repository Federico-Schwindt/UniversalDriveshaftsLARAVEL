/* Universal Driveshafts — cart line editing without a page reload */
(function () {
  'use strict';
  var root = document.querySelector('[data-cart]');
  if (!root) return;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var money = function (n) { return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(body)
    }).then(function (r) { return r.json(); });
  }

  // the total: the parts plus, once arrival.js has it for the visitor's ZIP, the shipping (then it is an estimate and says so)
  var sum = Number(root.getAttribute('data-sum')) || 0, ship = null;
  function total() {
    root.querySelectorAll('[data-total]').forEach(function (el) { el.textContent = money(sum + (ship || 0)); });
    root.querySelectorAll('[data-totallbl],[data-shipnote]').forEach(function (el) { el.textContent = el.getAttribute(ship === null ? 'data-plain' : 'data-est'); });
  }
  function shipped(d) { ship = d && d.success ? Number(d.cost) || 0 : null; total(); }
  document.addEventListener('uds:cart-ship', function (e) { shipped(e.detail); });
  if (window.udsCartShip) shipped(window.udsCartShip);   // the answer came before this script ran

  function paint(data) {
    var subtotal = sum = Number(data.subtotal) || 0;
    root.querySelectorAll('[data-subtotal]').forEach(function (el) { el.textContent = money(subtotal); });
    total();
    if (data.count) document.dispatchEvent(new CustomEvent('uds:cart-painted'));
    var badge = document.querySelector('[data-cart-count]');
    if (badge) { badge.textContent = data.count; badge.hidden = !data.count; }

    var bar = root.querySelector('[data-freebar]');
    if (bar) {
      var min = Number(bar.dataset.min) || 0;
      // the server says what counts (tubing and complete driveshafts never do), so removing a tube moves the meter right
      var base = data.free_base != null ? Math.max(0, Number(data.free_base) || 0) : Math.max(0, subtotal - (parseFloat(bar.getAttribute('data-tube')) || 0));
      var tube = subtotal - base > 0.009;
      var left = Math.max(0, min - base);
      var t = window.udsT || function (x, r) { if (r) for (var k in r) x = x.split(':' + k).join(r[k]); return x; };
      bar.setAttribute('data-tube', Math.max(0, subtotal - base).toFixed(2));
      bar.querySelector('.freebar__fill').style.width = (min ? Math.min(100, Math.round(base / min * 100)) : 100) + '%';
      bar.classList.toggle('is-done', !tube && left <= 0);
      bar.querySelector('[data-freetext]').innerHTML = tube
        ? t('Tubing and driveshafts ship at the carrier rate and do not qualify for free shipping.')
        : left <= 0 ? t('Free US ground shipping applied.')
        : t(':amount more for free US ground shipping.', {amount: '<b>' + money(left) + '</b>'});
    }
    if (!data.count) location.reload();
  }

  function bind(line) {
    var key = line.dataset.key;
    var price = Number(line.dataset.price) || 0;
    var qtyEl = line.querySelector('[data-qty]');
    var totalEl = line.querySelector('[data-line-total]');

    function send(q) {
      line.classList.add('is-busy');
      post('/cart/update', { key: key, qty: q }).then(function (r) {
        line.classList.remove('is-busy');
        if (!r || !r.success) return;
        if (q <= 0) { line.remove(); } else { qtyEl.value = q; totalEl.textContent = money(price * q); }
        paint(r.data);
        if (q <= 0 && r.data && r.data.count && root.querySelector('.cjob')) refresh(null, 0);
      }).catch(function () { line.classList.remove('is-busy'); });
    }

    line.querySelectorAll('[data-q]').forEach(function (b) {
      b.addEventListener('click', function () {
        send(Math.max(0, (parseInt(qtyEl.value, 10) || 1) + parseInt(b.dataset.q, 10)));
      });
    });
    qtyEl.addEventListener('change', function () { send(Math.max(1, parseInt(qtyEl.value, 10) || 1)); });
    line.querySelector('[data-remove]').addEventListener('click', function () {
      line.classList.add('is-busy');
      post('/cart/remove', { key: key }).then(function (r) {
        line.remove();
        if (r && r.success) paint(r.data);
        // "Complete the job" was worked out from the old cart: redraw it so it only offers parts that fit what is left
        if (r && r.success && r.data && r.data.count && root.querySelector('.cjob')) refresh(null, 0);
      }).catch(function () { line.classList.remove('is-busy'); });
    });
  }
  root.querySelectorAll('.cartline').forEach(bind);

  /* A part added on this page ("Complete the job", "Customers also bought", the part-number box) joins the list without
     a reload (owner, 2026-11-13: "se recarga la página"): totals from the add's answer at once, the new line and the
     suggestions from a fresh copy of the page; the suggestions wait until the button has shown "Added". */
  function refresh(data, moreDelay) {
    if (data) paint(data);
    var had = {};
    root.querySelectorAll('.cartline').forEach(function (l) { had[l.dataset.key] = true; });
    return fetch(location.pathname, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), fresh = doc.querySelector('[data-cart]');
        if (!fresh) { location.reload(); return; }
        var swap = function (sel) { var a = root.querySelector(sel), b = fresh.querySelector(sel); if (a && b) a.replaceWith(b); };
        swap('.cartlist'); swap('[data-freebar]');
        var row = root.querySelector('[data-subtotal]'), rowNew = fresh.querySelector('[data-subtotal]');
        if (row && rowNew) row.parentNode.replaceWith(rowNew.parentNode);
        root.querySelectorAll('.cartline').forEach(function (l) { bind(l); if (!had[l.dataset.key]) l.classList.add('is-new'); });
        root.setAttribute('data-sum', fresh.getAttribute('data-sum'));
        if (!data) paint({ subtotal: fresh.getAttribute('data-sum'), count: Number((doc.querySelector('[data-cart-count]') || {}).textContent) || 0 });
        setTimeout(function () { swap('.cartmore'); }, moreDelay || 0);
      }).catch(function () { location.reload(); });
  }
  window.udsCartRefresh = refresh;

  // "Complete the job" / "Customers also bought" under the cart
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cjob-add]'); if (!b) return;
    window.udsAdd(b, '/cart/add', { product_id: b.getAttribute('data-cjob-add'), quantity: 1 }).then(function (d) {
      if (d.ok) refresh(d.data, 1700);
    });
  });
})();
