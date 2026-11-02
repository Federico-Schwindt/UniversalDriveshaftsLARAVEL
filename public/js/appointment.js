/* UDS native appointment booker — replaces the FluentBooking iframe, same flow & rules. */
(function () {
  'use strict';
  var CFG = window.aptCfg, CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var root = document.getElementById('apt'); if (!root) return;
  var $ = function (s, r) { return (r || root).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || root).querySelectorAll(s)); };

  // Single fixed timezone — the shop's local time (Odessa / Tampa, FL = Eastern).
  var state = { service: null, tz: 'America/New_York',
    month: null, days: {}, date: null, slot: null, slotLabel: null };

  function show(step) {
    $$('.apt-step').forEach(function (s) { s.hidden = s.dataset.step !== step; });
  }
  function money(){}

  var RESCHEDULE = CFG.mode === 'reschedule';

  // ---- STEP 1: service ----
  $$('.apt-svc').forEach(function (b) {
    b.onclick = function () {
      state.service = b.dataset.service;
      var sm = $('[data-sum="service"]'); if (sm) sm.textContent = state.service;
      var su = $('#apt-summary'); if (su) su.hidden = false;
      show('datetime');
      state.month = startOfMonth(new Date());
      loadDays();
    };
  });
  $$('[data-back]').forEach(function (b) { b.onclick = function () { show(b.dataset.back); }; });

  // reschedule mode: calendar is already visible, just load
  if (RESCHEDULE) { state.month = startOfMonth(new Date()); loadDays(); }

  // ---- STEP 2: calendar ----
  function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1); }
  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

  function loadDays() {
    fetch(CFG.endpoints.days + '?timezone=' + encodeURIComponent(state.tz))
      .then(function (r) { return r.json(); }).then(function (d) { state.days = d.days || {}; renderCal(); });
  }
  $('#apt-prev').onclick = function () { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderCal(); };
  $('#apt-next').onclick = function () { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderCal(); };

  function renderCal() {
    var m = state.month;
    $('#apt-month').textContent = m.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    var grid = $('#apt-grid'); grid.innerHTML = '';
    var first = new Date(m.getFullYear(), m.getMonth(), 1);
    var startPad = first.getDay();
    var daysIn = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
    for (var i = 0; i < startPad; i++) grid.appendChild(document.createElement('span'));
    var todayStr = ymd(new Date());
    for (var day = 1; day <= daysIn; day++) {
      var dObj = new Date(m.getFullYear(), m.getMonth(), day), key = ymd(dObj);
      var cell = document.createElement('button');
      cell.type = 'button'; cell.className = 'apt-day'; cell.textContent = day;
      var avail = state.days[key] === true, past = key < todayStr;
      if (!avail || past) { cell.disabled = true; cell.classList.add('off'); }
      else {
        cell.classList.add('has');
        cell.onclick = (function (k, c) { return function () {
          $$('.apt-day.sel').forEach(function (x) { x.classList.remove('sel'); });
          c.classList.add('sel'); state.date = k; loadSlots(k);
        }; })(key, cell);
      }
      if (key === state.date) cell.classList.add('sel');
      grid.appendChild(cell);
    }
  }

  function loadSlots(date) {
    var box = $('#apt-times'), lbl = $('#apt-times-label');
    lbl.textContent = new Date(date + 'T00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    box.innerHTML = '<div class="apt-loading">Loading…</div>';
    fetch(CFG.endpoints.slots + '?date=' + date + '&timezone=' + encodeURIComponent(state.tz))
      .then(function (r) { return r.json(); }).then(function (d) {
        var slots = d.slots || [];
        if (!slots.length) { box.innerHTML = '<div class="apt-empty">No times available on this day.</div>'; return; }
        box.innerHTML = '';
        slots.forEach(function (s) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'apt-time'; b.textContent = s.label;
          b.onclick = function () {
            state.slot = s.start_utc; state.slotLabel = s.label;
            var sw = $('[data-sum="when"]'); if (sw) sw.textContent = lbl.textContent + ' · ' + s.label;
            if (RESCHEDULE) { submitReschedule(); return; }
            show('details');
          };
          box.appendChild(b);
        });
      });
  }

  function submitReschedule() {
    var body = new URLSearchParams({ token: CFG.token, start_utc: state.slot, timezone: state.tz });
    fetch(CFG.endpoints.reschedule, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: body })
      .then(function (r) { return r.json(); }).then(function (res) {
        if (!res.success) { alert((res.errors || ['Error'])[0]); loadDays(); return; }
        $('#apt-done-when').textContent = res.data.when;
        show('done');
      }).catch(function () { alert('Network error. Please try again.'); });
  }

  // ---- STEP 3: submit ----
  var submitBtn = $('#apt-submit');
  if (submitBtn) submitBtn.onclick = function () {
    var btn = this, form = $('#apt-form'), eb = $('#apt-errors');
    if (!form.reportValidity()) return;
    btn.disabled = true; btn.textContent = 'Booking…';
    var body = new URLSearchParams(new FormData(form));
    body.set('service', state.service); body.set('start_utc', state.slot); body.set('timezone', state.tz);
    fetch(CFG.endpoints.book, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: body })
      .then(function (r) { return r.json(); }).then(function (res) {
        if (!res.success) {
          eb.hidden = false; eb.innerHTML = (res.errors ? Object.values(res.errors).flat() : ['Something went wrong.']).map(function (e) { return '<div>' + e + '</div>'; }).join('');
          btn.disabled = false; btn.textContent = 'Schedule Appointment';
          window.scrollTo({ top: eb.getBoundingClientRect().top + scrollY - 120, behavior: 'smooth' });
          if (res.status === 422) loadDays();
          return;
        }
        $('#apt-done-when').textContent = res.data.service + ' — ' + res.data.when;
        $('#apt-done-ref').textContent = res.data.ref;
        $('#apt-summary').hidden = true;
        show('done');
      }).catch(function () { btn.disabled = false; btn.textContent = 'Schedule Appointment'; alert('Network error. Please try again.'); });
  };
})();
