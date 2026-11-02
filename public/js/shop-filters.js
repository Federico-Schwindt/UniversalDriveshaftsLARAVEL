/* Universal Driveshafts — catalog filter / sort / paginate (AJAX) */
(function () {
  'use strict';
  var box = document.getElementById('shop-table');
  var q = document.getElementById('shop-q');
  var sortSel = document.getElementById('shop-sort');
  if (!box || !q || !sortSel) return;
  var base = location.pathname;
  var filters = Array.prototype.slice.call(document.querySelectorAll('.shop-filter'));
  var timer, ctrl = null, seq = 0;
  var T = function (x) { return (window.UDS_T && window.UDS_T[x]) || x; };
  var hasSeriesSel = filters.some(function (s) { return s.dataset.name === 'series'; });
  // series-hub deep links (?series=1310) on a category without a series select: keep it across reloads
  function hubSeries() { return hasSeriesSel ? '' : (new URLSearchParams(location.search).get('series') || ''); }

  function params() {
    var p = new URLSearchParams();
    if (q.value.trim()) p.set('q', q.value.trim());
    filters.forEach(function (s) { if (s.value) p.set(s.dataset.name, s.value); });
    if (sortSel.value) p.set('sort', sortSel.value);
    if (hubSeries() && !p.has('series')) p.set('series', hubSeries());
    return p;
  }
  function load(p) {
    p = p || params();
    var url = base + '?' + p.toString();
    history.replaceState(null, '', url);
    box.classList.add('loading');
    // only the latest request may paint: abort the previous one and drop late answers (fast typing raced before)
    if (ctrl) ctrl.abort();
    ctrl = window.AbortController ? new AbortController() : null;
    var my = ++seq;
    fetch(url + '&_ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.text(); })
      .then(function (html) { if (my !== seq) return; box.innerHTML = html; box.classList.remove('loading'); })
      .catch(function (err) {
        if (my !== seq || (err && err.name === 'AbortError')) return;
        box.classList.remove('loading');   // keep the old table, say why it did not change
        var m = box.querySelector('.shop-err');
        if (!m) { m = document.createElement('p'); m.className = 'shop-err'; m.setAttribute('role', 'alert'); m.style.cssText = 'margin:0 0 .75rem;color:var(--danger,#b42318);font-size:.9rem'; box.insertBefore(m, box.firstChild); }
        m.textContent = T('Could not load the parts. Please try again.');
      });
  }

  q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 250); });
  document.getElementById('shop-q-btn').addEventListener('click', function () { load(); });
  filters.forEach(function (s) { s.addEventListener('change', function () { load(); }); });
  sortSel.addEventListener('change', function () { load(); });

  box.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-page],a[data-sort]');
    if (!a) return;
    e.preventDefault();
    var u = new URL(location.origin + base + a.getAttribute('href'));
    if (q.value.trim()) u.searchParams.set('q', q.value.trim()); else u.searchParams.delete('q');
    filters.forEach(function (s) { if (s.value) u.searchParams.set(s.dataset.name, s.value); });
    if (hubSeries() && !u.searchParams.has('series')) u.searchParams.set('series', hubSeries());
    load(u.searchParams);
    if (a.hasAttribute('data-page')) {
      window.scrollTo({ top: box.getBoundingClientRect().top + scrollY - 150, behavior: 'smooth' });
    }
  });
})();
