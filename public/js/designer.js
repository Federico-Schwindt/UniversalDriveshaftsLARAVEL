/* Driveshaft designer: measurement entry on the composed drawing (port of the supplier's tool).
   Values live in the URL hash (name=value&...) so a drawing can be shared or reopened.
   The measurement table on the sheet reads from the same dims and is rebuilt whenever they change. */
(function () {
  var root = document.getElementById('dsg'); if (!root) return;
  var svgBox = document.getElementById('dsg-svg'), input = document.getElementById('dsg-dim-input'), printBtn = document.getElementById('dsg-print');
  var tableBody = document.querySelector('#dsg-table tbody');
  var dims = {};
  var T = window.UDS_T || {}; function tx(s) { return T[s] || s; }
  document.querySelectorAll('[data-confirm]').forEach(function (a) { a.addEventListener('click', function (e) { if (!confirm(a.dataset.confirm)) e.preventDefault(); }); });
  if (printBtn) printBtn.addEventListener('click', function () { window.print(); });
  // save the drawing (sections + measurements) to the account; guests are sent to sign in and come back here
  document.querySelectorAll('#dsg-save, [data-dsg-save]').forEach(function (b) {
    b.addEventListener('click', function () {
      var main = document.getElementById('dsg-save'); if (!main) return;
      var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      var h = Object.keys(dims).map(function (k) { return k + '=' + encodeURIComponent(dims[k]); }).join('&');
      b.disabled = true;
      fetch('/driveshaft-designer/save', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body: new URLSearchParams({ style: main.dataset.style, sections: main.dataset.sections, dims: h }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (x) {
          b.disabled = false;
          if (x.d && x.d.login) { location.href = '/login?redirect=' + encodeURIComponent(location.pathname + location.hash); return; }
          if (x.ok && x.d.success) { var t = b.innerHTML; b.innerHTML = '<span>' + tx('Saved') + ' · ' + x.d.ref + ' — <u>' + tx('my designs') + '</u></span>'; b.classList.add('is-done'); b.onclick = function () { location.href = '/account/designs'; }; setTimeout(function () { b.classList.remove('is-done'); }, 6000); }
          else alert((x.d && x.d.message) || tx('Could not save the drawing.'));
        }).catch(function () { b.disabled = false; alert(tx('Could not save the drawing.')); });
    });
  });
  if (!svgBox) return;

  /* What each reference means, by style. One piece measures C/W/T only; two piece adds A and B and numbers front (1) and rear (2). */
  var DESC = root.dataset.style === '2' ? {
    A1: 'Overall length, front end to rear end',
    B1: 'Front end to center bearing', B2: 'Center bearing to rear end',
    C1: 'Front shaft, end to joint center', C2: 'Rear shaft, joint center to rear end',
    W1: 'Front tube length, weld to weld', W2: 'Rear tube length, weld to weld',
    T1: 'Front tube outside diameter', T2: 'Rear tube outside diameter'
  } : { C1: 'Overall length, end to end', W1: 'Tube length, weld to weld', T1: 'Tube outside diameter' };
  function describe(n) { return tx(DESC[n] || ({ A: 'Overall length', B: 'End to center bearing', C: 'Shaft length', W: 'Tube length, weld to weld', T: 'Tube outside diameter' }[n.charAt(0)] || 'Dimension')); }

  function readHash() { dims = {}; location.hash.replace('#', '').split('&').forEach(function (kv) { var p = kv.split('='); if (p[0] && p[1]) dims[p[0]] = decodeURIComponent(p[1]); }); }
  function updateSend() { var a = document.getElementById('dsg-send'); if (!a) return; var h = Object.keys(dims).map(function (k) { return k + '=' + dims[k]; }).join('&'); a.href = '/contact?designer=' + encodeURIComponent(a.dataset.sections) + (h ? '&dims=' + encodeURIComponent(h) : ''); }
  function writeHash() { updateSend(); var h = Object.keys(dims).map(function (k) { return k + '=' + encodeURIComponent(dims[k]); }).join('&'); if (location.hash !== '#' + h) history.replaceState(null, '', location.pathname + (h ? '#' + h : '')); }
  function strip(s) { var d = document.createElement('div'); d.innerHTML = s; return d.textContent || ''; }
  function labels() { return Array.prototype.slice.call(svgBox.querySelectorAll('.dim-text')); }
  function applyValues() {
    labels().forEach(function (t) { var n = t.getAttribute('name'); t.textContent = dims.hasOwnProperty(n) ? dims[n] : t.dataset.defaultText.toUpperCase(); t.classList.toggle('is-set', dims.hasOwnProperty(n)); });
    updateTable();
  }
  /* The sheet's measurement table: one row per label in the drawing, in drawing order. */
  function buildTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    labels().forEach(function (t) {
      var n = t.getAttribute('name'), tr = document.createElement('tr');
      tr.dataset.dim = n; tr.dataset.target = t.id;
      tr.innerHTML = '<th scope="row">' + n + '</th><td>' + describe(n) + '</td><td class="dsg__val is-blank"><span></span></td>';
      tr.addEventListener('click', function () { var lab = document.getElementById(tr.dataset.target); if (!lab) return; lab.scrollIntoView({ block: 'nearest' }); openInput(lab); });
      tableBody.appendChild(tr);
    });
  }
  function updateTable() {
    if (!tableBody) return;
    tableBody.querySelectorAll('tr').forEach(function (tr) { var n = tr.dataset.dim, cell = tr.querySelector('.dsg__val'), has = dims.hasOwnProperty(n); cell.classList.toggle('is-blank', !has); cell.firstChild.textContent = has ? dims[n] : ''; });
  }
  function setActive(t) {
    labels().forEach(function (l) { l.classList.toggle('is-active', l === t); });
    if (tableBody) tableBody.querySelectorAll('tr').forEach(function (tr) { tr.classList.toggle('is-active', !!t && tr.dataset.target === t.id); });
  }
  function openInput(t) {
    var r = t.getBoundingClientRect(), box = svgBox.getBoundingClientRect();
    input.hidden = false; input.value = dims[t.getAttribute('name')] || ''; input.dataset.target = t.id;
    input.style.width = Math.max(90, r.width + 40) + 'px';
    input.style.left = (r.left - box.left + r.width / 2 - input.offsetWidth / 2) + 'px';
    input.style.top = (r.top - box.top + r.height - input.offsetHeight) + 'px';
    input.placeholder = t.dataset.defaultText.replace(/[{}]/g, '') + ' ' + tx('in');
    setActive(t);
    input.focus(); input.select();
  }
  function commit() {
    var t = document.getElementById(input.dataset.target); if (!t) { input.hidden = true; setActive(null); return; }
    var n = t.getAttribute('name'), v = strip(input.value).trim();
    if (v) dims[n] = v; else delete dims[n];
    input.hidden = true; setActive(null); applyValues(); writeHash();
  }
  input.addEventListener('blur', commit);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault(); var t = document.getElementById(input.dataset.target); var next = +(t.dataset.tabIndex) + 1; commit();
      var nt = svgBox.querySelector('.dim-text[data-tab-index="' + next + '"]') || svgBox.querySelector('.dim-text[data-tab-index="1"]'); if (nt) openInput(nt);
    }
    if (e.key === 'Escape') { input.value = dims[document.getElementById(input.dataset.target).getAttribute('name')] || ''; input.blur(); }
  });
  svgBox.addEventListener('click', function (e) { var t = e.target.closest('.dim-text'); if (t) openInput(t); });
  window.addEventListener('resize', function () { if (!input.hidden) input.blur(); });
  window.addEventListener('hashchange', function () { readHash(); applyValues(); });

  /* On paper an empty {A1} becomes "A1 ______" so the value can be written by hand; the screen text comes back afterwards. */
  function paperMode(on) { if (on) { if (!input.hidden) input.blur(); labels().forEach(function (t) { if (!t.classList.contains('is-set')) t.textContent = t.dataset.defaultText.replace(/[{}]/g, '').toUpperCase() + ' ______'; }); } else applyValues(); }
  window.addEventListener('beforeprint', function () { paperMode(true); });
  window.addEventListener('afterprint', function () { paperMode(false); });
  if (window.matchMedia) { var pm = window.matchMedia('print'); (pm.addEventListener ? pm.addEventListener('change', function (e) { paperMode(e.matches); }) : pm.addListener(function (e) { paperMode(e.matches); })); }

  fetch(svgBox.dataset.url).then(function (r) { if (!r.ok) throw 0; return r.text(); }).then(function (txt) {
    var doc = new DOMParser().parseFromString(txt, 'image/svg+xml'), svg = doc.documentElement;
    svg.removeAttribute('width'); svg.removeAttribute('height'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', tx('Driveshaft drawing with dimensions'));
    svgBox.insertBefore(document.importNode(svg, true), input);
    svgBox.querySelectorAll('.dim-text').forEach(function (t, i) { t.dataset.tabIndex = i + 1; t.setAttribute('tabindex', '0'); t.dataset.defaultText = t.textContent.trim(); t.setAttribute('role', 'button'); t.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openInput(t); } }); });
    buildTable(); readHash(); applyValues(); updateSend(); root.classList.add('is-ready');
  }).catch(function () { svgBox.insertAdjacentHTML('afterbegin', '<p class="dsg__err">' + tx('The drawing could not be loaded. Please try again or call (813) 524-6491.') + '</p>'); });
})();
