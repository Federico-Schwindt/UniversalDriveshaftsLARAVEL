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

  function paint(data) {
    var subtotal = Number(data.subtotal) || 0;
    root.querySelectorAll('[data-subtotal],[data-total]').forEach(function (el) { el.textContent = money(subtotal); });
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

  root.querySelectorAll('.cartline').forEach(function (line) {
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
      }).catch(function () { line.classList.remove('is-busy'); });
    });
  });
})();
