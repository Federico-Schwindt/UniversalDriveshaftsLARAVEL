/* Universal Driveshafts — back-in-stock "notify me" forms and the "get a quote" modal for unpriced driveshafts. */
(function () {
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function post(url, data) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data) })
      .then(function (r) { return r.json().then(function (d) { d._status = r.status; return d; }); });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function show(el, html, err) { if (!el) return; el.hidden = false; el.innerHTML = html; el.classList.toggle('is-err', !!err); }
  function formData(f) { var o = {}; new FormData(f).forEach(function (v, k) { o[k] = v; }); return o; }
  var EMAIL = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

  /* ---- notify me (PDP) ---- */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-notify]'); if (!f) return;
    e.preventDefault();
    var msg = f.querySelector('.rcv-notify__msg'), btn = f.querySelector('button[type="submit"]'), em = f.querySelector('input[name="email"]');
    if (!em || !EMAIL.test(em.value.trim())) { show(msg, 'Enter a valid e-mail address.', true); if (em) em.focus(); return; }
    var t = btn.textContent; btn.disabled = true; btn.textContent = 'Saving…';
    post(f.getAttribute('action') || '/alerts/subscribe', formData(f)).then(function (d) {
      if (d.ok) {
        f.classList.add('is-done'); var row = f.querySelector('.rcv-notify__row'); if (row) row.hidden = true;
        show(msg, esc(d.message) + (d.in_stock && d.url ? ' <a href="' + esc(d.url) + '">Open the part</a>' : ''), false);
      } else { btn.disabled = false; btn.textContent = t; show(msg, esc(d.message || 'Something went wrong. Please try again.'), true); }
    }).catch(function () { btn.disabled = false; btn.textContent = t; show(msg, 'Network error. Please try again or call (813) 524-6491.', true); });
  });

  /* ---- get a quote (finder table + finder part page) ---- */
  var modal = null, lastFocus = null;
  function build() {
    modal = document.createElement('div');
    modal.className = 'rcv-modal'; modal.hidden = true;
    modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-labelledby', 'rcv-q-title');
    modal.innerHTML =
      '<div class="rcv-modal__card">' +
        '<button type="button" class="rcv-modal__x" data-rcv-close aria-label="Close">&times;</button>' +
        '<p class="rcv-modal__kicker">Priced on request</p>' +
        '<h2 id="rcv-q-title">Get a quote for <span data-q-sku></span></h2>' +
        '<p class="rcv-modal__lead">Tell us where it goes and how to reach you. A driveline tech prices it and replies, usually within one business day. In a hurry? Call <a href="tel:+18135246491">(813) 524-6491</a>.</p>' +
        '<form class="rcv-qform" novalidate>' +
          '<input type="hidden" name="part_number"><input type="text" name="website" class="rcv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">' +
          '<div class="field"><label for="rcv-q-vehicle">Vehicle (year, make, model, drive)</label><input id="rcv-q-vehicle" name="vehicle" maxlength="160" autocomplete="off" placeholder="2011 International 4300, 2WD"></div>' +
          '<div class="rcv-qform__2">' +
            '<div class="field"><label for="rcv-q-email">E-mail *</label><input id="rcv-q-email" type="email" name="email" required autocomplete="email" inputmode="email" placeholder="you@email.com"></div>' +
            '<div class="field"><label for="rcv-q-phone">Phone (optional)</label><input id="rcv-q-phone" type="tel" name="phone" autocomplete="tel" placeholder="(813) 524-6491"></div>' +
          '</div>' +
          '<div class="field"><label for="rcv-q-note">Anything else? (optional)</label><textarea id="rcv-q-note" name="note" maxlength="500" rows="2" placeholder="Center bearing number, measurements, when you need it…"></textarea></div>' +
          '<p class="rcv-notify__msg" role="alert" hidden></p>' +
          '<div class="rcv-qform__acts"><button type="submit" class="btn btn-primary">Send quote request</button><button type="button" class="btn btn-ghost" data-rcv-close>Cancel</button></div>' +
        '</form>' +
        '<div class="rcv-qdone" hidden>' +
          '<div class="rcv-qdone__ic" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>' +
          '<h3 style="margin:0">Request sent</h3><p data-q-done></p>' +
          '<button type="button" class="btn btn-primary" data-rcv-close>Done</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    var form = modal.querySelector('.rcv-qform');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.querySelector('.rcv-notify__msg'), btn = form.querySelector('button[type="submit"]'), em = form.querySelector('input[name="email"]');
      if (!EMAIL.test(em.value.trim())) { show(msg, 'Enter a valid e-mail address so we can send the quote.', true); em.focus(); return; }
      var t = btn.textContent; btn.disabled = true; btn.textContent = 'Sending…';
      post('/alerts/quote', formData(form)).then(function (d) {
        btn.disabled = false; btn.textContent = t;
        if (d.ok) { form.hidden = true; var done = modal.querySelector('.rcv-qdone'); done.hidden = false; done.querySelector('[data-q-done]').textContent = d.message || 'We will be in touch shortly.'; done.querySelector('[data-rcv-close]').focus(); }
        else show(msg, esc(d.message || 'Something went wrong. Please try again.'), true);
      }).catch(function () { btn.disabled = false; btn.textContent = t; show(msg, 'Network error. Please try again or call (813) 524-6491.', true); });
    });
  }
  function open(sku, pn, vehicle) {
    if (!modal) build();
    lastFocus = document.activeElement;
    var form = modal.querySelector('.rcv-qform'), done = modal.querySelector('.rcv-qdone');
    form.hidden = false; done.hidden = true; form.reset();
    form.querySelector('.rcv-notify__msg').hidden = true;
    modal.querySelector('[data-q-sku]').textContent = sku;
    form.querySelector('input[name="part_number"]').value = pn || sku;
    form.querySelector('input[name="vehicle"]').value = vehicle || '';
    modal.hidden = false; document.documentElement.classList.add('rcv-lock');
    requestAnimationFrame(function () { modal.classList.add('on'); (vehicle ? form.querySelector('input[name="email"]') : form.querySelector('input[name="vehicle"]')).focus(); });
  }
  function close() {
    if (!modal || modal.hidden) return;
    modal.classList.remove('on'); document.documentElement.classList.remove('rcv-lock');
    setTimeout(function () { modal.hidden = true; }, 200);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-quote]');
    if (b) { e.preventDefault(); open(b.getAttribute('data-quote'), b.getAttribute('data-pn'), b.getAttribute('data-vehicle')); return; }
    if (modal && !modal.hidden && (e.target === modal || e.target.closest('[data-rcv-close]'))) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  window.udsQuote = open;
})();
