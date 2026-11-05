{{-- Website traffic from Google Analytics (TrafficController): a blue globe of where visitors are (last 28 days, or right
     now) beside a list of those places, then the numbers that matter. Loaded after the page: the dashboard never waits on Google. --}}
<style>
.tr-top{display:grid;grid-template-columns:400px minmax(0,1fr);gap:22px;padding:16px 20px 6px;align-items:start}
.tr-globe{position:relative;width:400px;height:400px}
.tr-globe__gl{position:absolute;inset:0}
.tr-globe canvas{display:block;cursor:grab}
.tr-bar-top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.tr-live{display:inline-flex;align-items:center;gap:9px;font:600 14px var(--font-h);color:var(--ink-2)}
.tr-live b{font-size:22px;color:var(--ink);font-variant-numeric:tabular-nums}
.tr-dot{width:10px;height:10px;border-radius:50%;background:#e5484d;box-shadow:0 0 0 0 rgba(229,72,77,.55);animation:tr-pulse 1.6s ease-out infinite}
@keyframes tr-pulse{to{box-shadow:0 0 0 10px rgba(229,72,77,0)}}
.tr-mode{display:inline-flex;background:var(--paper-3);border-radius:999px;padding:3px}
.tr-mode button{border:0;background:none;color:var(--ink-3);font:600 13px var(--font-h);padding:6px 13px;border-radius:999px;cursor:pointer}
.tr-mode button.is-on{background:#fff;color:var(--blue);box-shadow:0 1px 3px rgba(11,26,51,.12)}
.tr-mode button:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
/* the places list: the readable side of the globe */
.tr-list{list-style:none;margin:0;padding:0;max-height:300px;overflow-y:auto;border:1px solid var(--line);border-radius:12px}
.tr-list li{border-top:1px solid var(--line-2)}
.tr-list li:first-child{border-top:0}
.tr-list button{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:4px 10px;align-items:center;width:100%;text-align:left;border:0;background:none;padding:8px 12px;font:inherit;color:var(--ink);cursor:pointer}
.tr-list button:hover,.tr-list button.is-on{background:var(--blue-050)}
.tr-list button:focus-visible{outline:2px solid var(--blue);outline-offset:-2px}
.tr-list .rk{font:600 12px var(--font-h);color:var(--ink-4);font-variant-numeric:tabular-nums}
.tr-list .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}
.tr-list .nm small{color:var(--ink-3);margin-left:6px}
.tr-list .vl{font-weight:700;font-variant-numeric:tabular-nums;font-size:14px}
.tr-list .br{grid-column:2 / -1;height:4px;border-radius:3px;background:var(--paper-3);overflow:hidden}
.tr-list .br i{display:block;height:100%;background:var(--blue)}
.tr-list .live .br i{background:#e5484d}
.tr-pick{margin-top:10px;min-height:56px;padding:10px 14px;border-radius:12px;background:var(--paper-2);font-size:13.5px;color:var(--ink-2)}
.tr-pick b{color:var(--ink);font-size:15px}
.tr-body{padding:6px 20px 18px;display:flex;flex-direction:column;gap:16px}
.tr-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
.tr-k{border:1px solid var(--line);border-radius:12px;padding:10px 12px;min-width:0}
.tr-k span{display:block;font-size:12px;color:var(--ink-3);font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.tr-k b{display:block;font:700 20px var(--font-h);font-variant-numeric:tabular-nums;margin-top:2px;white-space:nowrap}
.tr-k small{font-size:12px;font-weight:600}
.tr-k small.up{color:var(--ok)}.tr-k small.down{color:var(--err)}
.tr-h{font:700 12.5px var(--font-h);text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);margin:0 0 6px}
.tr-cols{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.tr-bars{display:flex;flex-direction:column;gap:6px;font-size:13.5px}
.tr-bar{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:2px 10px;align-items:center}
.tr-bar em{grid-column:1 / -1;height:6px;border-radius:4px;background:var(--paper-3);overflow:hidden;font-style:normal}
.tr-bar em i{display:block;height:100%;background:var(--blue);border-radius:4px}
.tr-bar span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tr-bar b{font-variant-numeric:tabular-nums}
.tr-dev{display:flex;height:10px;border-radius:6px;overflow:hidden;background:var(--paper-3)}
.tr-dev i{display:block;height:100%}
.tr-msg{padding:30px 20px;color:var(--ink-3);text-align:center}
@media(max-width:1200px){.tr-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.tr-cols{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:860px){.tr-top{grid-template-columns:1fr}.tr-globe{width:min(340px,100%);height:340px;margin:0 auto}}
@media(max-width:560px){.tr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.tr-cols{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){.tr-dot{animation:none}}
</style>
<div class="card mb" data-traffic>
  <div class="card__head"><h2>Website traffic</h2><span class="muted small" data-tr-sub>Google Analytics · loading…</span></div>
  <div class="tr-top">
    <div class="tr-globe" role="img" aria-label="Globe with the places visitors come from"><div class="tr-globe__gl" id="tr-globe-gl"></div></div>
    <div>
      <div class="tr-bar-top">
        <span class="tr-live"><span class="tr-dot" aria-hidden="true"></span><b data-tr-live>–</b> on the site now</span>
        <div class="tr-mode" role="group" aria-label="Show"><button type="button" class="is-on" data-tr-mode="28d">Last 28 days</button><button type="button" data-tr-mode="live">Right now</button></div>
      </div>
      <ol class="tr-list" data-tr-list aria-label="Places visitors are in"><li><span class="tr-msg" style="display:block">Loading…</span></li></ol>
      <div class="tr-pick" data-tr-pick aria-live="polite">Pick a place on the list or the globe to see its numbers.</div>
    </div>
  </div>
  <div class="tr-body" data-tr-side></div>
</div>
@push('scripts')
<script src="/js/vendor/globe.gl-2.46.2.min.js"></script>
<script>
(function () {
  var box = document.querySelector('[data-traffic]'); if (!box) return;
  var side = box.querySelector('[data-tr-side]'), sub = box.querySelector('[data-tr-sub]'), liveEl = box.querySelector('[data-tr-live]');
  var list = box.querySelector('[data-tr-list]'), pick = box.querySelector('[data-tr-pick]'), el = document.getElementById('tr-globe-gl');
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
  var n = function (v) { return Math.round(v).toLocaleString('en-US'); };
  var money = function (v) { return '$' + Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); };
  var data = null, mode = '28d', globe = null, sel = null;
  var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var GOLD = '#ffb81c', RED = '#e5484d';
  var HINT = 'Pick a place on the list or the globe to see its numbers.';

  function delta(c, p) {
    if (!p) return '';
    var d = (c - p) / p * 100;
    return '<small class="' + (d >= 0 ? 'up' : 'down') + '">' + (d >= 0 ? '▲' : '▼') + ' ' + Math.abs(d).toFixed(0) + '%</small>';
  }
  function bars(rows, label, val) {
    var max = Math.max.apply(null, rows.map(val).concat([1]));
    return '<div class="tr-bars">' + rows.map(function (r) { return '<div class="tr-bar"><span title="' + esc(label(r)) + '">' + esc(label(r)) + '</span><b>' + n(val(r)) + '</b><em><i style="width:' + (val(r) / max * 100).toFixed(1) + '%"></i></em></div>'; }).join('') + '</div>';
  }
  /* the numbers under the globe */
  function renderBody(d) {
    var c = d.kpi.cur, p = d.kpi.prev, conv = c.sessions ? c.ecommercePurchases / c.sessions * 100 : 0, pconv = p.sessions ? p.ecommercePurchases / p.sessions * 100 : 0;
    var mins = Math.floor(c.averageSessionDuration / 60) + 'm ' + Math.round(c.averageSessionDuration % 60) + 's';
    var devTot = d.devices.reduce(function (s, r) { return s + r[1]; }, 0) || 1, devCol = { mobile: '#1246ab', desktop: '#1f9e88', tablet: '#ffb81c' };
    side.innerHTML =
      '<div class="tr-kpis">' +
        '<div class="tr-k"><span>Visitors</span><b>' + n(c.activeUsers) + '</b>' + delta(c.activeUsers, p.activeUsers) + '</div>' +
        '<div class="tr-k"><span>Sessions</span><b>' + n(c.sessions) + '</b>' + delta(c.sessions, p.sessions) + '</div>' +
        '<div class="tr-k"><span>Page views</span><b>' + n(c.screenPageViews) + '</b>' + delta(c.screenPageViews, p.screenPageViews) + '</div>' +
        '<div class="tr-k"><span>Engaged</span><b>' + (c.engagementRate * 100).toFixed(0) + '%</b>' + delta(c.engagementRate, p.engagementRate) + '</div>' +
        '<div class="tr-k"><span>Purchases</span><b>' + n(c.ecommercePurchases) + '</b>' + delta(c.ecommercePurchases, p.ecommercePurchases) + '</div>' +
        '<div class="tr-k" title="Purchases per session"><span>Conversion</span><b>' + conv.toFixed(2) + '%</b>' + delta(conv, pconv) + '</div>' +
      '</div>' +
      '<div class="tr-cols">' +
        '<div><p class="tr-h">Where they come from</p>' + bars(d.channels, function (r) { return r[0]; }, function (r) { return r[1]; }) + '</div>' +
        '<div><p class="tr-h">Top countries</p>' + bars(d.countries.slice(0, 8), function (r) { return r[0]; }, function (r) { return r[2]; }) + '</div>' +
        '<div><p class="tr-h">Most viewed pages</p>' + bars(d.pages.slice(0, 6), function (r) { return (r[1] || r[0]).replace(/\s*[–|-]\s*Universal Driveshafts.*$/i, ''); }, function (r) { return r[2]; }) +
          '<p class="tr-h" style="margin-top:14px">Devices · avg. visit ' + mins + '</p><div class="tr-dev">' + d.devices.map(function (r) { return '<i style="width:' + (r[1] / devTot * 100).toFixed(1) + '%;background:' + (devCol[r[0]] || '#8a94a6') + '"></i>'; }).join('') + '</div>' +
          '<div class="muted small" style="margin-top:6px">' + d.devices.map(function (r) { return esc(r[0]) + ' ' + (r[1] / devTot * 100).toFixed(0) + '%'; }).join(' · ') + '</div></div>' +
      '</div>';
    sub.textContent = 'Google Analytics · last 28 days vs the 28 before · revenue tracked ' + money(c.purchaseRevenue);
  }
  /* the list beside the globe: 28-day places, or who is on the site now */
  function current() { return mode === 'live' ? data.live.points : data.points; }
  function renderList() {
    var pts = current().slice().sort(function (a, b) { return b.users - a.users; }).slice(0, 40);
    var max = pts.length ? pts[0].users : 1;
    list._pts = pts;
    if (!pts.length) { list.innerHTML = '<li><span class="tr-msg" style="display:block">' + (mode === 'live' ? 'Nobody on the site this minute.' : 'No places yet.') + '</span></li>'; return; }
    list.innerHTML = pts.map(function (p, i) {
      var parts = p.label.split(', '), city = parts.length > 1 ? parts.slice(0, -1).join(', ') : parts[0], country = parts.length > 1 ? parts[parts.length - 1] : '';
      return '<li class="' + (mode === 'live' ? 'live' : '') + '"><button type="button" data-i="' + i + '"><span class="rk">' + (i + 1) + '</span><span class="nm">' + esc(city) + (country ? '<small>' + esc(country) + '</small>' : '') + '</span><span class="vl">' + n(p.users) + '</span><span class="br"><i style="width:' + (p.users / max * 100).toFixed(1) + '%"></i></span></button></li>';
    }).join('');
  }
  function select(p) {
    sel = p;
    list.querySelectorAll('button[data-i]').forEach(function (b) { b.classList.toggle('is-on', list._pts && list._pts[+b.dataset.i] === p); });
    if (!p) { pick.textContent = HINT; return; }
    var sum = function (pts) { return pts.filter(function (q) { return q.label === p.label; }).reduce(function (s, q) { return s + q.users; }, 0); };
    var month = sum(data.points), now = sum(data.live.points);
    pick.innerHTML = '<b>' + esc(p.label) + '</b><br>' + n(month) + ' visitor' + (month === 1 ? '' : 's') + ' in the last 28 days · ' + n(now) + ' on the site now' + (p.exact ? '' : ' <span class="muted">(shown at the country centre)</span>');
    if (globe) { globe.controls().autoRotate = false; globe.pointOfView({ lat: p.lat, lng: p.lng, altitude: 1.8 }, 900); globe.pointsData(current()); }
  }
  list.addEventListener('click', function (e) { var b = e.target.closest('button[data-i]'); if (b && list._pts) select(list._pts[+b.dataset.i]); });

  function setLive(l) {
    liveEl.textContent = n(l.total);
    if (!globe) return;
    globe.ringsData(l.points);
    if (mode === 'live') { globe.pointsData(l.points); renderList(); }
  }
  function drawGlobe() {
    if (typeof Globe !== 'function') { el.innerHTML = '<div class="tr-msg">The globe could not load.</div>'; return; }
    globe = Globe()(el).width(400).height(400).backgroundColor('rgba(0,0,0,0)')
      .showAtmosphere(true).atmosphereColor('#7fa8ff').atmosphereAltitude(0.12)
      .hexPolygonResolution(3).hexPolygonMargin(0.4).hexPolygonUseDots(true).hexPolygonColor(function () { return 'rgba(255,255,255,.72)'; })
      .pointLat('lat').pointLng('lng')
      .pointColor(function (d) { return d === sel ? '#ffffff' : (mode === 'live' ? RED : GOLD); })
      .pointAltitude(function (d) { return Math.min(0.08, 0.008 + Math.sqrt(d.users) / (mode === 'live' ? 60 : 420)); })
      .pointRadius(function (d) { return (d === sel ? 0.25 : 0) + Math.min(0.75, 0.3 + Math.sqrt(d.users) / 70); })
      .pointLabel(function (d) { return '<div style="font:600 12.5px system-ui;padding:5px 8px;background:#fff;color:#0b1630;border-radius:7px;box-shadow:0 6px 16px rgba(0,0,0,.2)">' + esc(d.label) + ' · ' + n(d.users) + '</div>'; })
      .onPointClick(function (d) { select(d); })
      .ringLat('lat').ringLng('lng').ringColor(function () { return function (t) { return 'rgba(229,72,77,' + (1 - t) + ')'; }; })
      .ringMaxRadius(3).ringPropagationSpeed(1.5).ringRepeatPeriod(1400);
    globe.globeMaterial().color.set('#1246ab');   // UDS blue
    globe.pointOfView({ lat: 22, lng: -82, altitude: 2.35 }, 0);
    var ctl = globe.controls(); ctl.autoRotate = !still; ctl.autoRotateSpeed = 0.4; ctl.enableZoom = true;
    fetch('/data/countries-110m.geojson').then(function (r) { return r.json(); }).then(function (g) { globe.hexPolygonsData(g.features.filter(function (f) { return f.properties.ISO_A2 !== 'AQ'; })); });
    var mq = window.matchMedia('(max-width:860px)'), fit = function () { var s = mq.matches ? Math.min(340, el.parentNode.clientWidth || 340) : 400; globe.width(s).height(s); };
    if (mq.addEventListener) mq.addEventListener('change', fit); fit();
  }
  box.querySelectorAll('[data-tr-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      mode = b.getAttribute('data-tr-mode');
      box.querySelectorAll('[data-tr-mode]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
      sel = null; pick.textContent = HINT;
      if (data) { renderList(); if (globe) globe.pointsData(current()); }
    });
  });
  fetch('/admin/traffic/data', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
    if (d.error) { side.innerHTML = '<div class="tr-msg">' + esc(d.error) + '</div>'; list.innerHTML = ''; sub.textContent = 'Google Analytics'; return; }
    data = d; renderBody(d); renderList(); drawGlobe(); if (globe) globe.pointsData(d.points); setLive(d.live);
    // who is on the site right now, every minute
    setInterval(function () { fetch('/admin/traffic/live', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (l) { if (l.live) { data.live = l.live; setLive(l.live); } }).catch(function () {}); }, 60000);
  }).catch(function () { side.innerHTML = '<div class="tr-msg">Google Analytics did not answer. Try again in a minute.</div>'; });
})();
</script>
@endpush
