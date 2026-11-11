/* "Arrives Thursday, Nov 14 to 33556 with UPS Ground": product buy box and cart summary ([data-arrive]).
   The ZIP is the visitor's last one (localStorage "uds.zip", shared with the shipping estimator and the checkout) or,
   signed in, the account's saved one (data-zip). GET /api/arrival (CommerceController::arrival, no session: a slow
   background call must never write back a stale session over an Add to cart); data-id = one part, data-items = the
   cart's parts (id:qty,…), data-built = the cart holds a custom build. Owner, 2026-11-11.
   No ZIP yet: the IP's approximate ZIP (/geo, once a week per browser) shows a date marked "near you"; "Use my location"
   asks the browser (only on a tap, never on page load) and /api/zip-near turns the position into a ZIP. */
(function () {
  'use strict';
  var KEY = 'uds.zip';
  var T = window.udsT || function (s, r) { if (r) for (var k in r) s = s.split(':' + k).join(r[k]); return s; };
  var ICON = '<svg class="arrive__i" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v9H3zM14 9h4l3 3v3h-7zM7 18.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function zipOf(el) {
    try { var z = localStorage.getItem(KEY); if (/^\d{5}$/.test(z || '')) return z; } catch (e) {}
    return /^\d{5}$/.test(el.getAttribute('data-zip') || '') ? el.getAttribute('data-zip') : '';
  }
  var GEO = !!navigator.geolocation;
  function locBtn() { return GEO ? '<button type="button" class="arrive__loc">' + T('Use my location') + '</button>' : ''; }
  function idle(el) {
    el.innerHTML = ICON + '<span class="arrive__t">' + T('Delivery date:') + '</span>' + locBtn() + '<button type="button" class="arrive__ask">' + T('Enter ZIP') + '</button>';
  }
  function locate(el) {
    el.innerHTML = ICON + '<span>' + T('Finding your ZIP…') + '</span>';
    navigator.geolocation.getCurrentPosition(function (pos) {
      fetch('/api/zip-near?' + new URLSearchParams({ lat: pos.coords.latitude, lng: pos.coords.longitude }), { headers: { 'Accept': 'application/json' }, credentials: 'omit' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.success) { try { localStorage.setItem(KEY, d.zip); } catch (x) {} document.dispatchEvent(new CustomEvent('uds:zip', { detail: d.zip })); }
          else failed(el);
        }).catch(function () { failed(el); });
    }, function () { failed(el); }, { enableHighAccuracy: false, timeout: 12000, maximumAge: 864e5 });
  }
  function failed(el) { ask(el, ''); el.querySelector('.arrive__err').textContent = T('Could not get your location. Enter your ZIP code.'); }
  // the approximate ZIP of the visitor's internet connection, asked once a week per browser
  function ipZip(done) {
    try { var c = JSON.parse(localStorage.getItem('uds.zip.ip') || 'null'); if (c && Date.now() - c.t < 6048e5) return done(c.zip); } catch (e) {}
    fetch('/geo', { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (g) {
      var z = g && g.country === 'US' && /^\d{5}$/.test(g.zip || '') ? g.zip : '';
      try { localStorage.setItem('uds.zip.ip', JSON.stringify({ zip: z, t: Date.now() })); } catch (e) {}
      done(z);
    }).catch(function () { done(''); });
  }
  function ask(el, zip) {
    el.innerHTML = ICON + '<form class="arrive__f" novalidate><label><span class="sr-only">' + T('Delivery date for ZIP') + '</span>' +
      '<input type="text" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" autocomplete="postal-code" placeholder="' + T('ZIP code') + '" value="' + esc(zip) + '"></label>' +
      '<button type="submit" class="arrive__go">' + T('Show date') + '</button>' + locBtn() + '<span class="arrive__err" role="alert"></span></form>';
    var inp = el.querySelector('input');
    inp.addEventListener('input', function () { inp.value = inp.value.replace(/\D/g, '').slice(0, 5); });
    inp.focus();
  }
  function load(el, zip, approx) {
    if (el.hasAttribute('data-built')) { el.innerHTML = ICON + '<span class="arrive__t">' + T('Custom builds ship when they are finished.') + '</span>'; return; }
    el.classList.add('is-loading');
    el.innerHTML = ICON + '<span>' + T('Checking the delivery date…') + '</span>';
    var q = { zip: zip }; if (el.getAttribute('data-id')) q.id = el.getAttribute('data-id'); else q.items = el.getAttribute('data-items') || '';
    fetch('/api/arrival?' + new URLSearchParams(q), { headers: { 'Accept': 'application/json' }, credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        el.classList.remove('is-loading');
        if (d && d.success) {
          el.innerHTML = ICON + '<span class="arrive__t">' + T('Arrives :date', { date: '<b>' + esc(d.date) + '</b>' }) +
            ' <small>' + T('to :zip with :service', { zip: esc(d.zip), service: esc(d.service) }) + (approx ? ' (' + T('near you') + ')' : '') + '</small></span>' +
            '<button type="button" class="arrive__chg">' + T('Change') + '</button>';
        } else if (d && d.need_zip) idle(el);
        else if (d && d.built) el.innerHTML = ICON + '<span class="arrive__t">' + T('Custom builds ship when they are finished.') + '</span>';
        else el.hidden = true;   // no transit time from the carrier: the checkout still shows the rates
      })
      .catch(function () { el.classList.remove('is-loading'); el.hidden = true; });
  }
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-arrive]'));
  boxes.forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (e.target.closest('.arrive__loc')) locate(el);
      else if (e.target.closest('.arrive__ask')) ask(el, '');
      else if (e.target.closest('.arrive__chg')) ask(el, zipOf(el));
    });
    el.addEventListener('submit', function (e) {
      e.preventDefault();
      var z = (el.querySelector('input') || {}).value || '';
      if (!/^\d{5}$/.test(z)) { el.querySelector('.arrive__err').textContent = T('Enter a 5-digit US ZIP code.'); return; }
      try { localStorage.setItem(KEY, z); } catch (x) {}
      document.dispatchEvent(new CustomEvent('uds:zip', { detail: z }));
    });
    var z = zipOf(el);
    if (z) load(el, z);
    else ipZip(function (ip) { if (ip) load(el, ip, true); else idle(el); });
  });
  // a ZIP typed here or in the shipping estimator updates every box on the page
  document.addEventListener('uds:zip', function (e) { boxes.forEach(function (el) { el.hidden = false; load(el, e.detail); }); });
})();
