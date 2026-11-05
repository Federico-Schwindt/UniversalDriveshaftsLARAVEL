/* ---------- freight modal: fresh Warp quotes for the confirmed load, then a confirmed booking ---------- */
document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-modal="freight"]')) return;
  setTimeout(function () {
    var form = document.querySelector('.modal [data-freight-form]'); if (!form) return;
    var list = form.querySelector('[data-fr-rates]'), sum = form.querySelector('[data-fr-sum]'), book = form.querySelector('[data-fr-book]');
    var getBtn = form.querySelector('[data-fr-get]'), confirm = form.querySelector('[data-fr-confirm]'), cls = form.querySelector('[data-fr-class]');
    var rates = [], fetchedAt = 0, HOLD_MS = 14 * 60 * 1000, busy = false;
    var v = function (n) { var el = form.querySelector('[name=' + n + ']'); return el ? el.value : ''; };

    // NMFC density bands — the same table WarpFreightService::freightClass uses
    var BANDS = [[50,50],[35,55],[30,60],[22.5,65],[15,70],[13.5,85],[12,92.5],[10.5,100],[9,110],[8,125],[7,150],[6,175],[5,200],[4,250],[3,300],[2,400]];
    function density() {
      var w = +v('weight_lb'), cf = (+v('length_in')) * (+v('width_in')) * (+v('height_in')) / 1728;
      if (!w || !cf) { cls.textContent = '—'; return; }
      var d = w / cf, c = 500; for (var i = 0; i < BANDS.length; i++) if (d >= BANDS[i][0]) { c = BANDS[i][1]; break; }
      cls.textContent = d.toFixed(2) + ' lb/ft³ · class ' + c + ' · ' + cf.toFixed(1) + ' ft³';
    }

    function stale() { rates = []; fetchedAt = 0; setPick(null); list.innerHTML = '<div class="lbl__empty">The load, date or services changed — get fresh prices.</div>'; }

    function setPick(r) {
      ['quote_id', 'carrier', 'service_level', 'price', 'transit_days'].forEach(function (n) { form.querySelector('[name=' + n + ']').value = r ? (r[n] == null ? '' : r[n]) : ''; });
      if (!r) { sum.textContent = 'Get prices, pick a carrier'; }
      else {
        var pallets = +v('pallets');
        sum.innerHTML = admEsc(r.carrier + (r.service_level ? ' ' + r.service_level : '')) + ' · <b>$' + r.price.toFixed(2) + '</b>' + ' · ' + pallets + ' pallet' + (pallets === 1 ? '' : 's') + ' · pickup ' + admEsc(form.querySelector('[name=pickup_date] option:checked').textContent);
        form.querySelector('[data-fr-confirm-text]').textContent = 'I confirm: book ' + r.carrier + ' for $' + r.price.toFixed(2) + '. It is charged to the shop\'s Warp account and the carrier is dispatched to Odessa on ' + form.querySelector('[name=pickup_date] option:checked').textContent + ', ' + v('pickup_ready') + '–' + v('pickup_close') + ' ET.';
      }
      gate();
    }

    function gate() {
      var fresh = fetchedAt && (Date.now() - fetchedAt) < HOLD_MS;
      if (fetchedAt && !fresh) { list.insertAdjacentHTML('afterbegin', '<div class="lbl__empty" style="color:var(--err)">These prices have expired — get fresh ones.</div>'); fetchedAt = 0; }
      book.disabled = !(form.querySelector('[name=quote_id]').value && confirm.checked && fresh);
    }

    function render() {
      if (!rates.length) return;
      var html = '';
      rates.forEach(function (r, i) {
        html += '<label class="lbl__rate"><input type="radio" name="_fr" value="' + i + '"' + (i === 0 ? ' checked' : '') + '>'
          + '<b>' + admEsc(r.carrier) + (r.service_level ? ' <span class="muted" style="font-weight:500">' + admEsc(r.service_level) + '</span>' : '') + '</b>'
          + (r.fedex ? '<i>FedEx Freight</i>' : (i === 0 ? '<i>Cheapest</i>' : ''))
          + (r.transit_days != null ? '<span class="muted small" style="margin-left:auto;margin-right:10px">' + r.transit_days + ' day' + (r.transit_days === 1 ? '' : 's') + '</span>' : '')
          + '<em>$' + r.price.toFixed(2) + '</em></label>';
      });
      list.innerHTML = html;
      setPick(rates[0]);
    }

    getBtn.addEventListener('click', function () {
      if (busy) return;
      if (!form.reportValidity()) { confirm.required = true; return; }
      busy = true; getBtn.disabled = true; list.innerHTML = '<div class="lbl__empty">Asking every LTL carrier on this lane… about 15 seconds.</div>';
      var body = new FormData(form); body.delete('confirm');
      fetch('/x/', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) { list.innerHTML = '<div class="lbl__empty" style="color:var(--err)">' + admEsc(d.error || (d.message) || 'No quote.') + '</div>'; setPick(null); return; }
          rates = d.options || []; fetchedAt = Date.now();
          if (!rates.length) { list.innerHTML = '<div class="lbl__empty">' + admEsc(d.error || 'No bookable carrier for this lane and date.') + '</div>'; setPick(null); return; }
          render();
          if (d.test) list.insertAdjacentHTML('afterbegin', '<div class="lbl__empty" style="color:var(--ink-2)"><b>Sandbox prices</b> — booking is simulated: no charge, no truck.</div>');
        })
        .catch(function () { list.innerHTML = '<div class="lbl__empty" style="color:var(--err)">Could not reach Warp. Try again.</div>'; })
        .then(function () { busy = false; getBtn.disabled = false; });
    });

    list.addEventListener('change', function (e) { var r = e.target.closest('[name=_fr]'); if (r) setPick(rates[+r.value]); });
    form.querySelectorAll('[data-fr-load]').forEach(function (el) { el.addEventListener('input', function () { density(); if (fetchedAt) stale(); }); el.addEventListener('change', function () { density(); if (fetchedAt) stale(); }); });
    ['pickup_ready', 'pickup_close'].forEach(function (n) { form.querySelector('[name=' + n + ']').addEventListener('change', function () { if (form.querySelector('[name=quote_id]').value) setPick(rates.filter(function (x) { return x.quote_id === v('quote_id'); })[0]); }); });
    confirm.addEventListener('change', gate);
    form.addEventListener('submit', function (e) { gate(); if (book.disabled) { e.preventDefault(); return; } book.disabled = true; book.textContent = 'Booking…'; });
    setInterval(gate, 30000);
    density();
  }, 30);
});
