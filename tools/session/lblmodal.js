/* ---------- label modal: live prices from every carrier for the box typed in ---------- */
document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-modal="label"]')) return;
  setTimeout(function () {
    var form = document.querySelector('.modal [data-label-form]'); if (!form) return;
    var carrier = form.querySelector('[data-label-carrier]');
    var list = form.querySelector('[data-label-services]');
    var kinds = form.querySelectorAll('[data-label-kind]');
    var notify = form.querySelector('[data-label-notify]');
    var sum = form.querySelector('[data-label-sum]');
    var buy = form.querySelector('[data-label-buy]');
    var paid = list.getAttribute('data-paid') || '';
    var rateT = null, rates = [], freight = [], picked = '';

    function key(r) { return r.carrier + '|' + r.serviceCode; }

    function updateSum() {
      var r = form.querySelector('[name=service_code]:checked');
      if (!r) { sum.textContent = 'Pick a service'; buy.disabled = true; carrier.value = ''; return; }
      picked = r.value;
      carrier.value = r.getAttribute('data-carrier') || '';
      buy.disabled = false;
      var row = rates.filter(function (x) { return key(x) === picked; })[0];
      sum.innerHTML = row ? admEsc(row.carrierName + ' ' + row.serviceName) + ' · <b>$' + row.cost.toFixed(2) + '</b>' : '';
    }

    function render() {
      if (!rates.length) {
        list.innerHTML = '<div class="lbl__empty">No prices for this box and address. Check the weight and size.</div>';
        updateSum(); return;
      }
      var html = '';
      rates.forEach(function (r, i) {
        var k = key(r);
        var isPaid = paid && r.quoteId === paid;
        var sel = picked ? picked === k : (isPaid || i === 0);
        html += '<label class="lbl__rate"><input type="radio" name="service_code" value="' + admEsc(r.serviceCode) + '" data-carrier="' + admEsc(r.carrier) + '" data-key="' + admEsc(k) + '" required' + (sel ? ' checked' : '') + '>'
          + '<b>' + admEsc(r.carrierName + ' ' + r.serviceName) + '</b>'
          + (isPaid ? '<i>Customer paid this</i>' : (i === 0 ? '<i>Cheapest</i>' : ''))
          + '<em>$' + r.cost.toFixed(2) + '</em></label>';
      });
      if (freight.length) {
        html += '<p class="lbl__h" style="margin-top:14px">LTL freight · quote only</p>';
        freight.forEach(function (f) {
          html += '<div class="lbl__rate" style="opacity:.75;cursor:default"><b>' + admEsc(f.label) + '</b>'
            + (f.transit_days ? '<i>' + f.transit_days + ' days</i>' : '')
            + '<em>$' + Number(f.cost).toFixed(2) + '</em></div>';
        });
        html += '<p class="small muted" style="margin:6px 0 0">Freight is booked with Warp, not bought here — these prices are what the pallet really costs.</p>';
      }
      list.innerHTML = html;
      // the radios were just rebuilt: read the chosen one back off the DOM
      var cur = form.querySelector('[name=service_code]:checked');
      picked = cur ? cur.getAttribute('data-key') : '';
      updateSum();
    }

    function loadRates(now) {
      clearTimeout(rateT);
      rateT = setTimeout(function () {
        if (!form.querySelector('[name=weight_lb]').value) { rates = []; render(); return; }
        list.innerHTML = '<div class="lbl__empty">Loading prices…</div>';
        var body = new FormData();
        body.append('_token', document.querySelector('meta[name=csrf-token]').content);
        ['weight_lb', 'length_in', 'width_in', 'height_in'].forEach(function (n) {
          var el = form.querySelector('[name=' + n + ']'); if (el && el.value) body.append(n, el.value);
        });
        fetch('/x/', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { rates = d.rates || []; freight = d.freight || []; render(); })
          .catch(function () { rates = []; freight = []; render(); });
      }, now === true ? 0 : 450);
    }

    list.addEventListener('change', function (e) {
      var r = e.target.closest('[name=service_code]');
      picked = r ? r.getAttribute('data-key') : picked;
      updateSum();
    });
    form.querySelectorAll('[data-label-box]').forEach(function (i) { i.addEventListener('input', function () { picked = ''; loadRates(); }); });
    kinds.forEach(function (k) { k.addEventListener('change', function () { var ret = form.querySelector('[data-label-kind]:checked').value === 'return'; if (notify) notify.hidden = ret; }); });
    loadRates(true);
  }, 30);
});
