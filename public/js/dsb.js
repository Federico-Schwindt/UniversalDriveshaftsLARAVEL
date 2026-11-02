/* UDS Driveshaft Builder — Laravel port of DSBBuilder (ubs-driveshaft-builder v2.9.1)
   All flow, labels, pricing display and validation ported from SPEC-FRONTEND/SPEC-BACKEND.
   Final price is authoritative on the SERVER (/dsb/submit recomputes); client price is display. */
(function () {
  'use strict';
  const CFG = window.dsbConfig;
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const wrap = document.getElementById('ubs-dsb');
  if (!wrap || !CFG) return;

  // Display-only translation (window.UDS_T is filled by the page for the visitor's language).
  // Values kept in `state` and sent to the server stay in English: the shop reads them, and the
  // premade lookup matches the series label text.
  const T = window.UDS_T || {};
  const t = (s, vars) => { let o = T[s] || s; if (vars) Object.entries(vars).forEach(([k, v]) => { o = o.split(':' + k).join(v); }); return o; };
  const tl = (s) => { if (!s) return s; if (T[s]) return T[s]; const m = /^(.+) Series$/.exec(s); return m && T[':x Series'] ? T[':x Series'].split(':x').join(m[1]) : s; };
  const I18N = {
    pleaseSelect: t('Please complete the driveshaft configuration before adding to cart.'),
    copied: t('Share link copied to clipboard.'),
    noResults: t('No series match your search.'),
    completeAll: t('Complete all the steps above to enable checkout.'),
    readyToAdd: t('Ready. Click below to add this driveshaft to your cart.'),
    notSelected: t('Not selected'), pending: t('Pending'), ready: t('Ready'),
    required: t('Please fill in the required fields.'),
    networkError: t('Network error. Please try again.'),
  };
  const LM_SERIES = ['1310', '1330', '1350', '1410', '1480', '1550'];
  const $ = (s, r) => (r || wrap).querySelector(s);
  const $$ = (s, r) => Array.from((r || wrap).querySelectorAll(s));
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = (n) => CFG.currency + Number(n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  const state = {
    step: 16, buildMode: 'custom', buildModeChosen: false, materialKey: null,
    premadeProduct: null, premadeExtras: [],
    dutyKey: null, dutyLabel: null, seriesKey: null, seriesLabel: null,
    subcatKey: null, subcatLabel: null,
    frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null,
    search: '', price: 0, certifiedPrice: 0, brandNewPrice: 0, priceTier: 'certified',
    flangeAddon: {label: '', price: 0, value: ''}, transAddon: {label: '', price: 0, value: ''},
    tubeAddon: {label: '', price: 0}, extras: {a1: '', c1: '', b1: '', length: '', notes: ''},
  };
  const cat = {filters: {flange: {}, trans: {}}, page: {flange: 1, trans: 1}};
  const pm = {length: '', page: 1, data: {}};

  // ---------- helpers ----------
  function toast(msg) {
    let t = $('.dsb-toast');
    if (!t) { t = document.createElement('div'); t.className = 'dsb-toast'; wrap.appendChild(t); }
    t.textContent = msg; t.classList.add('show');
    clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 2500);
  }
  const dutyOf = () => CFG.tree[state.dutyKey] || null;
  function seriesMap() {
    const d = dutyOf(); if (!d) return {};
    if (d.subcategories) return state.subcatKey ? (d.subcategories[state.subcatKey]?.series || {}) : {};
    return d.series || {};
  }
  const isAluminumEligible = () => LM_SERIES.includes(state.seriesKey);
  function frontAppNeedsCatalog() {
    if (state.dutyKey === 'light_medium') return ['trans', 'flange'].includes(state.frontAppKey);
    return state.frontAppKey === 'flange';
  }
  function rearAppNeedsCatalog() {
    if (state.dutyKey === 'light_medium') return ['flange', 'trans'].includes(state.rearAppKey);
    return state.rearAppKey === 'flange';
  }
  function activeSteps() {
    const steps = [16, 1];
    if (dutyOf()?.subcategories) steps.push(13);
    steps.push(2);
    if (state.buildMode === 'premade') { steps.push(17, 18, 6); return steps; }
    if (isAluminumEligible()) steps.push(19);
    // each yoke is offered right after it is chosen (owner, 2026-10-25): front, its add-on, rear, its add-on, measurements
    steps.push(14);
    if (frontAppNeedsCatalog()) { steps.push(7); if (state.flangeChoice === 'yes') steps.push(9); }
    steps.push(15);
    if (rearAppNeedsCatalog()) { steps.push(10); if (state.transChoice === 'yes') steps.push(12); }
    steps.push(4, 6);
    return steps;
  }

  // ---------- pricing (display mirror; server recomputes at submit) ----------
  function pickOdRow(rows, a1) {
    if (!rows || !rows.length) return null;
    for (const r of rows) {
      const max = parseFloat(r.max_a1 || 0);
      if (!max || !a1 || a1 <= max) return r;
    }
    return rows[rows.length - 1];
  }
  function computePrice() {
    if (state.buildMode === 'premade') {
      const base = state.premadeProduct ? parseFloat(state.premadeProduct.price || 0) : 0;
      const extras = state.premadeExtras.reduce((s, e) => s + (parseFloat(e.cost) || 0), 0);
      state.certifiedPrice = state.brandNewPrice = state.price = base + extras;
      renderPrice(); return;
    }
    // verbatim mirror of the original computePrice (dsb-builder.js:2566-2617)
    let cert = CFG.basePrice, brand = CFG.basePrice;
    const s = seriesMap()[state.seriesKey];
    if (s) {
      if (s.price && parseFloat(s.price) > 0) cert = parseFloat(s.price);
      if (s.new_price && parseFloat(s.new_price) > 0) brand = parseFloat(s.new_price);
    }
    const skLow = (state.seriesKey || '').toLowerCase();
    const table = state.materialKey === 'aluminum' ? CFG.aluminumPricing : CFG.tubeOdPricing;
    if (state.materialKey === 'aluminum' && CFG.aluminumFlatPricing?.[skLow]) {
      const f = CFG.aluminumFlatPricing[skLow];
      if (parseFloat(f.cert_price) > 0) cert = parseFloat(f.cert_price);
      if (parseFloat(f.new_price) > 0) brand = parseFloat(f.new_price);
    }
    const rows = (table || {})[skLow];
    if (rows && rows.length) {
      const a1 = parseFloat(state.extras.a1) || 0;
      let row = rows.find((p) => p.max_a1 === 0 || parseFloat(p.max_a1) >= a1);
      if (!row) row = rows[rows.length - 1];
      if (row) {
        if (parseFloat(row.cert_price) > 0) cert = parseFloat(row.cert_price);
        if (parseFloat(row.new_price) > 0) brand = parseFloat(row.new_price);
        state.tubeOd = row.od || '';
      }
    }
    const addons = (state.tubeAddon.price || 0) + (state.flangeAddon.price || 0) + (state.transAddon.price || 0);
    state.certifiedPrice = cert + addons; state.brandNewPrice = brand + addons;
    state.price = state.priceTier === 'new' ? state.brandNewPrice : state.certifiedPrice;
    renderPrice();
  }
  function renderPrice() {
    // before anything is chosen the price is 0 — never show the base product price
    const nothing = state.buildMode === 'premade' ? !state.premadeProduct : !state.seriesKey;
    const hero = nothing ? 0 : (state.buildMode === 'premade' ? state.price : state.certifiedPrice);
    $('.dsb-hero-price-value').textContent = money(hero);
    const tc = $('[data-tier-price="certified"]'), tn = $('[data-tier-price="new"]');
    if (tc) tc.textContent = money(nothing ? 0 : state.certifiedPrice);
    if (tn) tn.textContent = money(nothing ? 0 : state.brandNewPrice);
    const badge = $('[data-cart-price]'); if (badge) badge.textContent = money(nothing ? 0 : state.price);
    $$('[data-mbar-price]').forEach((el) => { el.textContent = money(hero); });
  }

  // ---------- crumbs ----------
  function crumbVals() {
    return {
      build_mode: state.buildModeChosen ? (state.buildMode === 'premade' ? t('Pre-Made') : t('Custom Build')) : '',
      duty: tl(state.dutyLabel) || '', series: tl(state.seriesLabel) || '',
      measure: state.extras.a1 ? `A1: ${state.extras.a1}"` : (state.premadeProduct && state.premadeA ? `A: ${state.premadeA}"` : ''),
      review: isDone() ? I18N.ready : I18N.pending,
    };
  }
  function updateCrumbs() {
    const vals = crumbVals();
    $$('.dsb-crumb').forEach((c) => {
      const f = c.dataset.field, v = vals[f] || '';
      const el = $('.dsb-crumb-value', c);
      el.textContent = v || I18N.notSelected;
      el.style.fontStyle = v ? 'normal' : 'italic';
      c.classList.toggle('completed', !!v && f !== 'review');
      c.classList.toggle('active', +c.dataset.goto === state.step);
    });
    renderSheet();
  }

  // ---------- step engine ----------
  // mobile rail: scroll so the crumb for the current step (or the last crumb
  // at or before it, for sub-steps without their own crumb) sits at the left
  function focusCrumb() {
    const rail = $('.dsb-crumbs');
    if (!rail || !matchMedia('(max-width:700px)').matches) return;
    const order = activeSteps(), i = order.indexOf(state.step);
    let target = null;
    $$('.dsb-crumb').forEach((c) => { const k = order.indexOf(+c.dataset.goto); if (k > -1 && k <= i) target = c; });
    if (!target) return;
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    rail.scrollTo({left: Math.max(0, target.offsetLeft - 16), behavior: reduce ? 'auto' : 'smooth'});
  }
  function showStep(n) {
    state.step = n;
    $$('.dsb-step').forEach((s) => s.classList.toggle('dsb-step--active', +s.dataset.step === n));
    updateCrumbs(); updateHidden(); focusCrumb();
    const r = wrap.getBoundingClientRect();
    if (r.top < 0 || r.top > innerHeight * 0.5) wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
  }
  function nextAfter(n) {
    const steps = activeSteps(); const i = steps.indexOf(n);
    return steps[Math.min(i + 1, steps.length - 1)];
  }
  function goBack(n) {
    if (n === 16) Object.assign(state, {dutyKey: null, dutyLabel: null, seriesKey: null, seriesLabel: null, subcatKey: null, subcatLabel: null, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null, materialKey: null, premadeProduct: null, premadeExtras: [], buildModeChosen: false, search: ''});
    else if (n === 1) Object.assign(state, {dutyKey: null, dutyLabel: null, seriesKey: null, seriesLabel: null, subcatKey: null, subcatLabel: null, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null, materialKey: null, premadeProduct: null});
    else if (n === 13) Object.assign(state, {seriesKey: null, seriesLabel: null, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null});
    else if (n === 2) Object.assign(state, {seriesKey: null, seriesLabel: null, materialKey: null, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null});
    else if (n === 19) Object.assign(state, {materialKey: null, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null});
    else if (n === 14) Object.assign(state, {frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null});
    else if (n === 15) Object.assign(state, {rearAppKey: null, rearAppLabel: null});
    else if (n === 17) { state.premadeProduct = null; state.premadeExtras = []; }
    if (n <= 7) state.flangeAddon = {label: '', price: 0, value: ''};
    if (n <= 10) state.transAddon = {label: '', price: 0, value: ''};
    computePrice(); showStep(n);
  }
  function goPrev() {
    const steps = activeSteps(); const i = steps.indexOf(state.step);
    goBack(steps[Math.max(0, i - 1)]);
  }

  // ---------- renderers ----------
  function renderDutyCards() {
    const box = $('#dsb-duty-cards'); box.innerHTML = '';
    Object.entries(CFG.tree).forEach(([key, d]) => {
      const count = d.series ? Object.keys(d.series).length : Object.values(d.subcategories || {}).reduce((s, sc) => s + Object.keys(sc.series || {}).length, 0);
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'dsb-card dsb-card--duty' + (key === 'heavy' ? ' dsb-card--heavy' : '');
      b.innerHTML = `<span class="dsb-card-visual">${key === 'heavy' ? '<svg viewBox="0 0 256 256" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M255.42,117l-14-35A15.93,15.93,0,0,0,226.58,72H192V64a8,8,0,0,0-8-8H32A16,16,0,0,0,16,72V184a16,16,0,0,0,16,16H49a32,32,0,0,0,62,0h50a32,32,0,0,0,62,0h17a16,16,0,0,0,16-16V120A7.94,7.94,0,0,0,255.42,117ZM192,88h34.58l9.6,24H192ZM32,72H176v64H32ZM80,208a16,16,0,1,1,16-16A16,16,0,0,1,80,208Zm81-24H111a32,32,0,0,0-62,0H32V152H176v12.31A32.11,32.11,0,0,0,161,184Zm31,24a16,16,0,1,1,16-16A16,16,0,0,1,192,208Zm48-24H223a32.06,32.06,0,0,0-31-24V128h48Z"/></svg>' : '<svg viewBox="0 0 256 256" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M207.06,72.67A111.24,111.24,0,0,0,128,40h-.4C66.07,40.21,16,91,16,153.13V176a16,16,0,0,0,16,16H224a16,16,0,0,0,16-16V152A111.25,111.25,0,0,0,207.06,72.67ZM224,176H119.71l54.76-75.3a8,8,0,0,0-12.94-9.42L99.92,176H32V153.13c0-3.08.15-6.12.43-9.13H56a8,8,0,0,0,0-16H35.27c10.32-38.86,44-68.24,84.73-71.66V80a8,8,0,0,0,16,0V56.33A96.14,96.14,0,0,1,221,128H200a8,8,0,0,0,0,16h23.67c.21,2.65.33,5.31.33,8Z"/></svg>'}</span>
        <span class="dsb-card-body"><span class="dsb-card-title">${esc(tl(d.label))}</span>
        <span class="dsb-card-desc">${esc(tl(d.description || ''))}</span>
        <span class="dsb-card-meta">${esc(t(':n series available', {n: count}))}</span></span><span class="dsb-card-arrow">›</span>`;
      b.onclick = () => {
        Object.assign(state, {dutyKey: key, dutyLabel: d.label, subcatKey: null, subcatLabel: null, frontAppKey: null, rearAppKey: null, search: ''});
        $('#dsb-series-search').value = '';
        if (d.subcategories) { renderSubcats(); showStep(13); }
        else { renderSeries(); showStep(2); }
      };
      box.appendChild(b);
    });
  }
  function renderSubcats() {
    const box = $('#dsb-subcat-cards'); box.innerHTML = '';
    Object.entries(dutyOf().subcategories).forEach(([key, sc]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'dsb-card';
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(tl(sc.label))}</span>
        ${sc.hint ? `<span class="dsb-card-desc">${esc(tl(sc.hint))}</span>` : ''}
        <span class="dsb-card-meta">${esc(t(':n series', {n: Object.keys(sc.series || {}).length}))}</span></span><span class="dsb-card-arrow">›</span>`;
      b.onclick = () => { state.subcatKey = key; state.subcatLabel = sc.label; renderSeries(); showStep(2); };
      box.appendChild(b);
    });
  }
  function renderSeries() {
    const box = $('#dsb-series-cards'); box.innerHTML = '';
    const all = Object.entries(seriesMap());
    const q = state.search.toLowerCase();
    const list = q ? all.filter(([k, s]) => (s.label + ' ' + (s.hint || '') + ' ' + k).toLowerCase().includes(q)) : all;
    $('[data-count]').textContent = q && list.length !== all.length ? t('Showing :a of :b series', {a: list.length, b: all.length}) : t(':n series', {n: all.length});
    if (!list.length) { box.innerHTML = `<p class="dsb-empty">${I18N.noResults}</p>`; return; }
    list.forEach(([key, s]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'dsb-card dsb-card--series';
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(tl(s.label))}</span>
        ${s.hint ? `<span class="dsb-card-desc">${esc(tl(s.hint))}</span>` : ''}</span>
        ${parseFloat(s.price) > 0 ? `<span class="dsb-card-price">${money(s.price)}</span>` : ''}<span class="dsb-card-arrow">›</span>`;
      b.onclick = () => pickSeries(key, s.label);
      box.appendChild(b);
    });
  }
  function pickSeries(key, label) {
    Object.assign(state, {seriesKey: key, seriesLabel: label, frontAppKey: null, frontAppLabel: null, rearAppKey: null, rearAppLabel: null, materialKey: null, premadeProduct: null});
    state.flangeAddon = {label: '', price: 0, value: ''}; state.transAddon = {label: '', price: 0, value: ''};
    computePrice();
    if (state.buildMode === 'premade') { pm.length = ''; pm.page = 1; showStep(17); loadPremades(); return; }
    if (isAluminumEligible()) { showStep(19); return; }
    state.materialKey = 'steel'; renderApps('front'); showStep(14);
  }
  function getRearApps() {
    const alu = state.materialKey === 'aluminum';
    const front = state.frontAppKey;
    const hideCarriers = alu || ['slip_flange', 'slip_no_flange'].includes(front) || front === 'trans';
    return Object.entries(CFG.rearApps).filter(([key, a]) => {
      if (a.aluminum_only) return alu && ['weld', 'flange'].includes(front);
      if (key.startsWith('carrier') && hideCarriers) return false;
      return true;
    });
  }
  function renderApps(which) {
    const box = $(which === 'front' ? '#dsb-front-app-cards' : '#dsb-rear-app-cards'); box.innerHTML = '';
    const entries = which === 'front'
      ? Object.entries(CFG.frontApps).filter(([, a]) => !(a.lm_only && state.dutyKey !== 'light_medium'))
      : getRearApps();
    entries.forEach(([key, a]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'dsb-app-card';
      const code = SECTION[which][key];
      b.innerHTML = `${code ? '<span class="dsb-app-card-img dsb-app-card-img--line"></span>' : (a.image ? `<span class="dsb-app-card-img"><img src="${esc(a.image)}" alt="${esc(tl(a.label))}" loading="lazy"></span>` : '')}<span class="dsb-app-card-label">${esc(tl(a.label))}</span>`;
      if (code) b.firstChild.appendChild(sectionArt(code, tl(a.label), which === 'rear' && FLIP[key]));
      b.onclick = () => {
        $$('.dsb-app-card', box.parentElement).forEach((c) => c.classList.remove('dsb-app-card--selected'));
        b.classList.add('dsb-app-card--selected');
        if (which === 'front') {
          Object.assign(state, {frontAppKey: key, frontAppLabel: a.label, rearAppKey: null, rearAppLabel: null});
          state.flangeAddon = {label: '', price: 0, value: ''}; state.transAddon = {label: '', price: 0, value: ''};
          state.flangeChoice = null; state.transChoice = null;
          renderApps('rear');
          if (frontAppNeedsCatalog()) { setupYokeAsk('flange'); showStep(7); } else showStep(15);
        } else {
          state.rearAppKey = key; state.rearAppLabel = a.label;
          state.transAddon = {label: '', price: 0, value: ''}; state.transChoice = null;
          if (rearAppNeedsCatalog()) { setupYokeAsk('trans'); showStep(10); } else afterRearYoke();
        }
        computePrice();
      };
      box.appendChild(b);
    });
  }


  // ---------- technical drawings (the driveshaft designer's own line drawings) ----------
  // Only where the designer section is the same part as the builder option. Carrier-bearing ends keep their photo.
  const SECTION = {
    front: {weld: '01', flange: '02', trans: '09', slip_flange: '11', slip_no_flange: '12'},
    rear: {weld: '01R', flange: '02R', slip_flange: '11R', slip_no_flange: '12R', carrier_end: '50', carrier_slip: '59'},
  };
  // the carrier-bearing ends are middle sections of the designer's two-piece shaft: 50 and 59 (owner, 2026-10-25)
  const FLIP = {};
  // the builder prices the front shaft only: its A1 runs to the bearing centre, which is the designer's B1.
  // The drawing is cut just after the part that belongs to this shaft (through the yoke after the bearing).
  const CARRIER = {carrier_end: {cutAt: 'C1', pad: 12}, carrier_slip: {cutAt: 'C1', pad: 12}};
  // builder field -> designer dimension, checked combination by combination against the builder's own diagrams:
  // normally A1 = A1 (overall) and C1 = C1 (joint centre to joint centre); a weld-to-weld shaft has no flange
  // faces, so the builder's A1 is the designer's C1 and its C1 the weld-to-weld W1.
  const DIMMAP = {weld__weld: {a1: 'C1', c1: 'W1'}, slip_no_flange__weld: {a1: 'C1'}};
  const DIMDESC = {
    A1: t('Overall length, from the flange face or the end of the slip yoke to the far end'),
    C1: t('U-joint center to U-joint center'),
    W1: t('Weld to weld (the length of the tube)'),
  };
  function planFor(front, rear) {
    const a = SECTION.front[front], b = SECTION.rear[rear];
    if (!a || !b || CFG.pieces === 'two') return null;
    if (CARRIER[rear]) return {sections: a + '+' + b + '+' + (front === 'flange' ? '02R' : '01R'), map: {a1: 'B1'}, cut: CARRIER[rear]};
    const m = DIMMAP[front + '__' + rear] || {a1: 'A1', c1: 'C1'};
    return {sections: a + '+' + b, map: m};
  }
  const svgCache = {};
  function fetchSvg(url) {
    if (!svgCache[url]) svgCache[url] = fetch(url, {credentials: 'same-origin'}).then((r) => { if (!r.ok) throw new Error('svg ' + r.status); return r.text(); });
    return svgCache[url];
  }
  // inline an SVG: its <style> rules are scoped to the box so they never touch the rest of the page
  function inlineSvg(box, text, scope) {
    const doc = new DOMParser().parseFromString(text, 'image/svg+xml');
    const svg = doc.documentElement;
    if (!svg || svg.nodeName.toLowerCase() !== 'svg') throw new Error('not svg');
    svg.querySelectorAll('script,foreignObject').forEach((n) => n.remove());
    svg.querySelectorAll('style').forEach((st) => {
      st.textContent = st.textContent.replace(/(^|})\s*([^{}@]+)\{/g, (m0, a, sel) => a + sel.split(',').map((x) => scope + ' ' + x.trim()).join(',') + '{');
    });
    svg.removeAttribute('width'); svg.removeAttribute('height');
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    box.innerHTML = ''; box.appendChild(document.importNode(svg, true));
    return box.querySelector('svg');
  }
  function sectionArt(code, alt, flip) {
    const box = document.createElement('span'); box.className = 'dsb-art' + (flip ? ' dsb-art--flip' : ''); box.setAttribute('role', 'img'); box.setAttribute('aria-label', alt);
    // one fixed window per canvas size, so every drawing shows at the same scale (owner: all cards the same size)
    fetchSvg('/img/designer/sections/' + code + '.svg').then((txt) => {
      const s = inlineSvg(box, txt, '.dsb-art');
      s.setAttribute('viewBox', (s.getAttribute('viewBox') || '').startsWith('0 0 150') ? '20 26 110 48' : '0 26 75 48');
    }).catch(() => {});
    return box;
  }
  const fmtVal = (v) => (v === '' || v == null || isNaN(+v) ? '' : String(+v) + '"');
  // composed drawing with only the builder's own dimensions, renamed to the builder's names
  function renderPlan(box, plan, editable) {
    return fetchSvg('/driveshaft-designer/svg/' + plan.sections + '?style=uds').then((txt) => {
      const svg = inlineSvg(box, txt, '.dsb-plan-svg');
      if (plan.cut) {
        // front shaft of a two-piece drawing: keep everything up to the cut, drop the rear shaft's labels
        const ref = svg.querySelector(`.dims text[name="${plan.cut.cutAt}"]`), line = ref && ref.parentNode.querySelector('.dim-line');
        const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
        if (line && vb.length === 4) {
          const x2 = Math.max(+line.getAttribute('x1'), +line.getAttribute('x2'));
          svg.setAttribute('viewBox', [vb[0], vb[1], x2 + plan.cut.pad - vb[0], vb[3]].join(' '));
          const ends = svg.querySelectorAll('.dims text.dim-text');
          ends.forEach((tx) => { if (tx.getAttribute('name') === plan.map.a1) tx.setAttribute('x', String((vb[0] + x2) / 2)); });
        }
        svg.querySelectorAll('text.front').forEach((f) => { if (/REAR/i.test(f.textContent)) f.remove(); });
        // the rear shaft's half of a paired dimension (B2 next to B1) starts where B1 ends: remove its lines
        const keepLine = svg.querySelector(`.dims text[name="${plan.map.a1}"]`), kl = keepLine && keepLine.parentNode.querySelector('.dim-line');
        if (kl) {
          const end = Math.max(+kl.getAttribute('x1'), +kl.getAttribute('x2'));
          keepLine.parentNode.querySelectorAll('line').forEach((ln) => { if (Math.min(+ln.getAttribute('x1'), +ln.getAttribute('x2')) >= end - 0.5 && ln !== kl && !ln.classList.contains('dim-ext')) ln.remove(); });
        }
      }
      const keep = {};
      Object.entries(plan.map).forEach(([field, dim]) => { if (field !== 'c1' || !$('#dsb-field-c1').hidden) keep[dim] = field; });
      svg.querySelectorAll('.dims > g').forEach((g) => {
        // a group can hold a pair of labels (B1 and B2 of a two-piece shaft): drop the ones the builder does not ask for
        g.querySelectorAll('text.dim-text').forEach((x) => { if (!keep[x.getAttribute('name')] && g.querySelectorAll('text.dim-text').length > 1) x.remove(); });
        const tx = g.querySelector('text.dim-text'); const dim = tx && tx.getAttribute('name');
        if (!dim || !keep[dim]) { g.remove(); return; }
        const field = keep[dim];
        tx.dataset.field = field; tx.dataset.label = field.toUpperCase();
        if (editable) { tx.setAttribute('tabindex', '0'); tx.setAttribute('role', 'button'); tx.setAttribute('aria-label', t('Enter :dim in inches', {dim: field.toUpperCase()})); }
      });
      // no "<<< FRONT <<<" / ">>> REAR >>>" captions anywhere in the builder (owner, 2026-10-25)
      svg.querySelectorAll('text.front').forEach((f) => f.remove());
      paintPlan(box);
      if (plan.cut) requestAnimationFrame(() => dropJoint(svg, plan));
      return svg;
    });
  }
  // after the bearing the shaft ends at its yoke: the U-joint cross and the other shaft's yoke are removed
  // (owner, 2026-10-25: "prueba eliminando la cruceta también")
  function dropJoint(svg, plan) {
    const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
    const ctm = svg.getScreenCTM(); if (!ctm || vb.length !== 4) return;
    const cxUnits = vb[0] + vb[2] - plan.cut.pad;              // the cut sits pad units past the cross centre
    const toScreen = (x) => { const p = svg.createSVGPoint(); p.x = x; p.y = 0; return p.matrixTransform(ctm).x; };
    const cx = toScreen(cxUnits), unit = Math.abs(toScreen(cxUnits + 1) - cx);
    if (!unit) return;
    const shapes = Array.from(svg.querySelectorAll('.parts path,.parts line,.parts polyline,.parts polygon,.parts rect,.parts ellipse,.parts circle'));
    // the cross: the round shape centred on the joint, and everything drawn inside it (the hatching).
    // This first, simple rule is the look the owner chose (2026-10-25); refinements left stray pieces.
    let r = 3.5 * unit;
    shapes.forEach((el) => { const b = el.getBoundingClientRect(); const c = b.left + b.width / 2;
      if (Math.abs(c - cx) < unit && Math.abs(b.width - b.height) < unit && b.width > 3 * unit && b.width < 14 * unit) r = Math.max(r, b.width / 2); });
    shapes.forEach((el) => {
      const b = el.getBoundingClientRect(); if (!b.width && !b.height) return;
      const inCross = b.left >= cx - r - unit * 0.4 && b.right <= cx + r + unit * 0.4;
      if (inCross || b.left + b.width / 2 > cx) el.remove();
    });
    svg.setAttribute('viewBox', [vb[0], vb[1], cxUnits + r / unit + 1 - vb[0], vb[3]].join(' '));
  }
  function paintPlan(box) {
    box.querySelectorAll('text.dim-text[data-field]').forEach((tx) => {
      const v = state.extras[tx.dataset.field];
      tx.textContent = v ? tx.dataset.label + ' = ' + fmtVal(v) : '{' + tx.dataset.label + '}';
      tx.classList.toggle('is-set', !!v);
    });
  }
  // click a dimension on the drawing and type it right there
  function openDimInput(tx) {
    const holder = $('#dsb-plan'), inp = $('#dsb-plan-input');
    const hb = holder.getBoundingClientRect(), r = tx.getBoundingClientRect();
    const f = tx.dataset.field;
    inp.dataset.field = f; inp.value = state.extras[f] || ''; inp.placeholder = f.toUpperCase();
    inp.hidden = false;
    inp.style.left = Math.max(4, Math.min(hb.width - 132, r.left - hb.left + r.width / 2 - 64)) + 'px';
    inp.style.top = Math.max(4, r.top - hb.top + r.height / 2 - 20) + 'px';
    $$('text.dim-text', holder).forEach((x) => x.classList.toggle('is-editing', x === tx));
    inp.focus(); inp.select();
  }
  function commitDim(move) {
    const inp = $('#dsb-plan-input'); if (inp.hidden) return;
    const f = inp.dataset.field, v = inp.value.trim().replace(',', '.').replace(/["\s]|in$/gi, '');
    if (v === '' || (+v > 0 && +v < 400)) {
      const field = $('#dsb-inp-' + f); field.value = v; field.dispatchEvent(new Event('input', {bubbles: true}));
    }
    inp.hidden = true; $$('text.dim-text.is-editing').forEach((x) => x.classList.remove('is-editing'));
    if (move) {
      const all = $$('#dsb-plan-svg text.dim-text[data-field]');
      const i = all.findIndex((x) => x.dataset.field === f); const next = all[i + (move > 0 ? 1 : -1)];
      if (next) openDimInput(next); else if (move > 0) $('[data-next="measure"]').focus();
    }
  }
  let planNow = null;
  function setupPlan() {
    const plan = planFor(state.frontAppKey, state.rearAppKey);
    planNow = plan;
    $('#dsb-plan').hidden = !plan;
    $('.dsb-measure-diagram').hidden = !!plan;
    ['a1', 'c1'].forEach((f) => {
      const dim = plan && plan.map[f];
      const hint = $('#dsb-field-' + f + ' .dsb-hint');
      if (hint) hint.textContent = dim ? DIMDESC[dim] : t('See diagram above for :dim measurement point.', {dim: f.toUpperCase()});
    });
    if (!plan) return;
    $('#dsb-plan-code').textContent = tl(state.frontAppLabel) + '  →  ' + tl(state.rearAppLabel);
    renderPlan($('#dsb-plan-svg'), plan, true).catch(() => { $('#dsb-plan').hidden = true; $('.dsb-measure-diagram').hidden = false; planNow = null; });
  }

  // ---------- build sheet (live summary beside the steps) ----------
  function renderSheet() {
    const box = $('#dsb-sheet-draw'); if (!box) return;
    const want = state.buildMode === 'premade' ? 'premade'
      : state.frontAppKey && state.rearAppKey ? 'plan:' + state.frontAppKey + '__' + state.rearAppKey
      : state.frontAppKey ? 'front:' + state.frontAppKey : 'none';
    if (box.dataset.shows !== want) {
      box.dataset.shows = want;
      const plan = planFor(state.frontAppKey, state.rearAppKey);
      if (want.startsWith('plan:') && plan) {
        box.innerHTML = '<div class="dsb-plan-svg"></div>';
        renderPlan(box.firstChild, plan, false).catch(() => { box.innerHTML = `<img src="${esc(currentDiagram())}" alt="">`; });
      } else if (want.startsWith('plan:')) {
        box.innerHTML = `<img src="${esc(currentDiagram())}" alt="${esc(t('Configuration diagram'))}">`;
      } else if (want.startsWith('front:') && SECTION.front[state.frontAppKey]) {
        box.innerHTML = ''; box.appendChild(sectionArt(SECTION.front[state.frontAppKey], tl(state.frontAppLabel)));
        const more = document.createElement('span'); more.className = 'dsb-sheet-hint'; more.textContent = t('Now pick the rear end'); box.appendChild(more);
      } else if (want === 'premade' && state.premadeProduct?.image) {
        box.innerHTML = `<img src="${esc(state.premadeProduct.image)}" alt="${esc(state.premadeProduct.name || '')}">`;
      } else if (want !== 'none' && state.frontAppKey) {
        const a = CFG.frontApps[state.frontAppKey]; box.innerHTML = a && a.image ? `<img src="${esc(a.image)}" alt="">` : '';
      } else {
        box.innerHTML = `<span class="dsb-sheet-empty">${esc(t('Your driveshaft is drawn here as you choose the yokes.'))}</span>`;
      }
    } else {
      const p = box.querySelector('.dsb-plan-svg'); if (p) paintPlan(p);
    }
    const rows = [
      [t('Build'), state.buildModeChosen ? (state.buildMode === 'premade' ? t('Pre-Made') : t('Custom Build')) : ''],
      [t('Duty'), tl(state.dutyLabel)], [t('Series'), tl(state.seriesLabel)],
      [t('Material'), state.buildMode === 'custom' && state.materialKey ? (state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel')) : ''],
      [t('Front'), state.buildMode === 'custom' ? tl(state.frontAppLabel) : ''], [t('Rear'), state.buildMode === 'custom' ? tl(state.rearAppLabel) : ''],
      ['A1', fmtVal(state.extras.a1)], ['C1', fmtVal(state.extras.c1)], [t('Tube OD'), state.tubeOd || ''],
      [t('Product'), state.premadeProduct?.name || ''],
    ];
    $('#dsb-sheet-specs').innerHTML = rows.filter(([, v]) => v).map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('')
      || `<div class="is-empty"><dt>${esc(t('Nothing chosen yet'))}</dt><dd></dd></div>`;
  }

  // ---------- measurements ----------
  function currentDiagram() {
    return CFG.appDiagrams[state.frontAppKey + '__' + state.rearAppKey] || '/img/builder/diagram-measure-takeoff-drivesahfts.webp';
  }
  function setupMeasure() {
    $('#dsb-diagram-img').src = currentDiagram();
    const c1hidden = (state.rearAppKey || '').startsWith('carrier') || (state.frontAppKey === 'slip_no_flange' && state.rearAppKey === 'weld');
    $('#dsb-field-c1').hidden = c1hidden; if (c1hidden) { state.extras.c1 = ''; $('#dsb-inp-c1').value = ''; }
    $('#dsb-slip-note').hidden = !['slip_flange', 'slip_no_flange'].includes(state.frontAppKey);
    updateMaterialWarning(); drawOverlay(); setupPlan();
  }
  function updateMaterialWarning() {
    const lim = CFG.materialSteelLimits[state.seriesKey];
    const a1 = parseFloat(state.extras.a1 || 0);
    $('#dsb-material-warning').hidden = !(state.materialKey === 'steel' && lim && a1 > lim);
  }
  function drawOverlay() {
    const img = $('#dsb-diagram-img'), cv = $('#dsb-diagram-canvas');
    if (!img || !cv || !img.complete || !img.naturalWidth) return;
    try {
      const w = img.clientWidth, h = img.clientHeight;
      cv.width = w; cv.height = h;
      const ctx = cv.getContext('2d');
      ctx.clearRect(0, 0, w, h);
      const off = document.createElement('canvas');
      off.width = img.naturalWidth; off.height = img.naturalHeight;
      const octx = off.getContext('2d'); octx.drawImage(img, 0, 0);
      const data = octx.getImageData(0, 0, off.width, off.height).data;
      const bands = [];
      for (let y = 0; y < off.height; y += 3) {
        let hits = 0, sx = 0;
        for (let x = 0; x < off.width; x += 4) {
          const i = (y * off.width + x) * 4, r = data[i], g = data[i + 1], b = data[i + 2];
          if (r > 170 && g > 50 && g < 200 && b < 80 && r - b > 120) { hits++; sx += x; }
        }
        if (hits > 8) bands.push({y, x: sx / hits});
      }
      const merged = [];
      bands.forEach((b) => {
        const last = merged[merged.length - 1];
        if (last && b.y - last.y2 < 18) { last.y2 = b.y; last.xs.push(b.x); }
        else merged.push({y1: b.y, y2: b.y, xs: [b.x]});
      });
      const vals = [state.extras.a1, state.extras.c1].filter((v) => v !== '');
      merged.slice(0, vals.length).forEach((band, i) => {
        const cx = (band.xs.reduce((s, x) => s + x, 0) / band.xs.length) * (w / off.width);
        const cy = ((band.y1 + band.y2) / 2) * (h / off.height);
        const text = vals[i] + '"';
        ctx.font = 'bold 14px Poppins, sans-serif';
        const tw = ctx.measureText(text).width + 16;
        ctx.fillStyle = '#fff'; ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath(); ctx.roundRect(cx - tw / 2, cy - 13, tw, 26, 13); ctx.fill(); ctx.stroke();
        ctx.fillStyle = '#0f172a'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(text, cx, cy);
      });
    } catch (e) { /* cross-origin — silently bail (as original) */ }
  }
  function measureValid() { return parseFloat(state.extras.a1 || 0) > 0; }
  function afterMeasure() {
    enterReview();
  }

  // ---------- yoke ask/catalog ----------
  function setupYokeAsk(which) {
    if (which === 'flange') {
      const l = tl(state.frontAppLabel || 'Flange Yoke');
      // :item_lc is the label in sentence case (Spanish writes "un yugo de brida", English "a Flange Yoke")
      const v = {item_lc: l.toLowerCase(), item: l};
      $('#dsb-yoke7-title').textContent = t('Do you need a :item?', v);
      $('#dsb-yoke7-desc').textContent = t('Pick a new :item from our catalog and see its measurements, or say no and we fit a good-condition one from our shop.', {item: l.toLowerCase()});
      $('#dsb-yoke7-add').textContent = t('Add a :item to the Order', v);
    } else {
      const l = tl(state.rearAppLabel || 'Transmission Yoke');
      // :item_lc is the label in sentence case (Spanish writes "un yugo de brida", English "a Flange Yoke")
      const v = {item_lc: l.toLowerCase(), item: l};
      $('#dsb-yoke10-title').textContent = t('Do you need a :item?', v);
      $('#dsb-yoke10-desc').textContent = t('Pick a new :item from our catalog and see its measurements, or say no and we fit a good-condition one from our shop.', {item: l.toLowerCase()});
      $('#dsb-yoke10-add').textContent = t('Add a :item to the Order', v);
    }
  }
  function afterFrontYoke() { showStep(15); }
  function afterRearYoke() { setupMeasure(); showStep(4); }
  function loadCatalog(section) {
    // verbatim quirk from the original: front section sends type=trans only when front app is trans;
    // the rear section ALWAYS queries the flange category.
    let type = 'flange';
    if (section === 'flange' && state.frontAppKey === 'trans') type = 'trans';
    const step = section === 'flange' ? 9 : 12;
    const root = $(`.dsb-catalog[data-catalog-section="${section}"]`);
    const grid = $('[data-catalog-grid]', root), pag = $('[data-catalog-pagination]', root), fl = $('[data-catalog-filters]', root);
    grid.innerHTML = `<div class="dsb-loading">${esc(t('Loading products...'))}</div>`; showStep(step);
    const params = new URLSearchParams({type, series: state.seriesKey || '', page: cat.page[section], per_page: 24, filters: JSON.stringify(cat.filters[section])});
    fetch(CFG.endpoints.yokes + '?' + params).then((r) => r.json()).then(({data}) => {
      // filters UI
      fl.innerHTML = '';
      Object.entries(data.filters || {}).forEach(([slug, values]) => {
        const label = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        const d = document.createElement('div');
        d.innerHTML = `<label>${esc(tl(label))}</label>`;
        const sel = document.createElement('select'); sel.className = 'dsb-input dsb-catalog-select';
        sel.innerHTML = `<option value="">${esc(t('All'))}</option>` + values.map((v) => `<option${cat.filters[section][slug] === v ? ' selected' : ''}>${esc(v)}</option>`).join('');
        sel.onchange = () => { if (sel.value) cat.filters[section][slug] = sel.value; else delete cat.filters[section][slug]; cat.page[section] = 1; loadCatalog(section); };
        d.appendChild(sel); fl.appendChild(d);
      });
      // grid
      if (!data.products.length) { grid.innerHTML = `<div class="dsb-empty">${esc(t('No products match your filters. Try adjusting the criteria above.'))}</div>`; pag.innerHTML = ''; return; }
      grid.innerHTML = '';
      data.products.forEach((p) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'dsb-catalog-card';
        // one fixed layout for every card: photo box, two-line name, four spec rows, price + select at the bottom
        const ttl = (k) => tl(k.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()));
        const specs = Object.entries(p.attributes || {}).slice(0, 4);
        while (specs.length < 4) specs.push(['', '']);
        let name = String(p.name || ''); const sku = String(p.sku || '');
        if (sku && name.toUpperCase().startsWith(sku.toUpperCase())) name = name.slice(sku.length).replace(/^[\s,|\u2013-]+/, '');
        b.innerHTML = `<span class="dsb-cat-img">${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.sku || '')}" loading="lazy" decoding="async">` : ''}</span>
          <span class="dsb-cat-sku">${esc(p.sku || '')}</span>
          <span class="dsb-cat-name" title="${esc(p.name)}">${esc(name || p.name)}</span>
          <span class="dsb-cat-specs">${specs.map(([k, v]) => `<span><i>${k ? esc(ttl(k)) : '&nbsp;'}</i><b>${esc(v)}</b></span>`).join('')}</span>
          <span class="dsb-cat-foot"><span class="dsb-cat-price">${money(p.price)}</span><span class="dsb-cat-select">${esc(t('Select'))}</span></span>`;
        const im = b.querySelector('.dsb-cat-img img'); if (im) im.addEventListener('error', () => im.remove());
        b.onclick = () => {
          const addon = {label: p.name, price: parseFloat(p.price || 0), value: 'catalog:' + p.id, product_id: p.id};
          if (section === 'flange') { state.flangeAddon = addon; computePrice(); afterFrontYoke(); }
          else { state.transAddon = addon; computePrice(); afterRearYoke(); }
        };
        grid.appendChild(b);
      });
      pag.innerHTML = '';
      if (data.pages > 1) {
        pag.innerHTML = `<button type="button" class="dsb-btn dsb-btn--secondary" data-pg="-1" ${data.page <= 1 ? 'disabled' : ''}>← ${esc(t('Prev'))}</button>
          <span>${esc(t('Page :p / :n (:t products)', {p: data.page, n: data.pages, t: data.total}))}</span>
          <button type="button" class="dsb-btn dsb-btn--secondary" data-pg="1" ${data.page >= data.pages ? 'disabled' : ''}>${esc(t('Next'))} →</button>`;
        $$('[data-pg]', pag).forEach((btn) => btn.onclick = () => { cat.page[section] += +btn.dataset.pg; loadCatalog(section); });
      }
    }).catch(() => { grid.innerHTML = `<div class="dsb-empty">${I18N.networkError}</div>`; });
  }

  // ---------- premade ----------
  function loadPremades() {
    const grid = $('#dsb-premade-grid'), pag = $('#dsb-premade-pagination');
    grid.innerHTML = `<div class="dsb-loading">${esc(t('Loading…'))}</div>`;
    const params = new URLSearchParams({series_label: state.seriesLabel || '', paged: pm.page, length_filter: pm.length});
    fetch(CFG.endpoints.premades + '?' + params).then((r) => r.json()).then(({data}) => {
      const sel = $('#dsb-premade-length-filter');
      if (sel.options.length <= 1 && data.length_options?.length) {
        data.length_options.forEach((v) => { const o = document.createElement('option'); o.value = v; o.textContent = v.endsWith('"') ? v : v + '"'; sel.appendChild(o); });
      }
      if (!data.products.length) { grid.innerHTML = `<div class="dsb-empty">${esc(t('No pre-made driveshafts found for the current filters. Go back and choose a different series, or clear the length filter.'))}</div>`; pag.innerHTML = ''; return; }
      grid.innerHTML = '';
      data.products.forEach((p) => {
        pm.data[p.id] = p;
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'dsb-premade-card'; b.title = p.name;
        b.innerHTML = `<span class="dsb-premade-img-wrap"><img src="${esc(p.image)}" alt="" loading="lazy"></span>
          <span class="dsb-premade-info"><span class="dsb-premade-name">${esc(p.name)}</span>
          <span class="dsb-premade-price">${money(p.price)}</span></span>
          <span class="dsb-premade-select-btn">${esc(t('Select this driveshaft'))}</span>`;
        b.onclick = () => pickPremade(p);
        grid.appendChild(b);
      });
      pag.innerHTML = '';
      if (data.pages > 1) {
        pag.innerHTML = `<button type="button" class="dsb-btn dsb-btn--secondary" data-pp="-1" ${pm.page <= 1 ? 'disabled' : ''}>← ${esc(t('Prev'))}</button>
          <span>${esc(t('Page :p of :n (:t items)', {p: pm.page, n: data.pages, t: data.total}))}</span>
          <button type="button" class="dsb-btn dsb-btn--secondary" data-pp="1" ${pm.page >= data.pages ? 'disabled' : ''}>${esc(t('Next'))} →</button>`;
        $$('[data-pp]', pag).forEach((btn) => btn.onclick = () => { pm.page += +btn.dataset.pp; loadPremades(); });
      }
    }).catch(() => { grid.innerHTML = `<div class="dsb-empty">${esc(t('Error loading products. Please try again.'))}</div>`; });
  }
  function pickPremade(p) {
    state.premadeProduct = p; state.frontAppKey = '_premade'; state.rearAppKey = '_premade';
    state.premadeExtras = []; computePrice(); renderPremadeMeasure(p); showStep(18);
  }
  function renderPremadeMeasure(p) {
    const box = $('#dsb-epo-fields-container');
    const noteA = T['__noteA'] || `<strong>Modified Vehicles:</strong> This driveshaft is designed to fit stock vehicle applications, double check your driveshaft length if your truck is lifted, leveled or modified. We may be able to make you a custom driveshaft to match your unique vehicle specifications — contact us today with your driveshaft length (<a href="/resources/one-piece-driveshaft" target="_blank">measuring guide</a>). The maximum length driveshaft that UDS can build is 89 inches seal to center of u-joint.`;
    const noteAB = T['__noteAB'] || `We invite you to proceed with your order today, and anticipate sending a follow-up communication to gather essential details. Alternatively, you may expedite the process by providing your measurements below. Please measure from the transfer case seal to the flat face of the differential, where the driveshaft's 4-bolt flange is mounted. Your driveshaft order remains pending until we receive this vital information.`;
    let html = `<div class="dsb-measure-diagram"><img id="dsb-premade-diagram" src="${p.has_ab ? CFG.diagramABUrl : CFG.diagramAUrl}" alt="${esc(t('How to measure'))}"></div>
      <div class="dsb-info-banner"><span>ⓘ</span><span>${p.has_ab ? noteAB : noteA}</span></div>
      <div class="dsb-fields">
      <div class="dsb-field"><label>${esc(t('Measurement A (inches)'))} </label>
        <input type="number" step="0.125" min="1" class="dsb-input" id="dsb-premade-a" placeholder="${esc(t('e.g. 48.5'))}">
        <div class="dsb-hint">${esc(t('See diagram above for Measurement A.'))}</div></div>`;
    if (p.has_ab) html += `<div class="dsb-field"><label>${esc(t('Measurement B (inches)'))}</label>
        <input type="number" step="0.125" min="1" class="dsb-input" id="dsb-premade-b" placeholder="${esc(t('e.g. 12.0'))}">
        <div class="dsb-hint">${esc(t('See diagram above for Measurement B.'))}</div></div>`;
    const extraDef = CFG.premadeExtraFields[p.id];
    if (extraDef) {
      html += `<div class="dsb-field"><label>${esc(tl(extraDef.label))} <span class="dsb-req">*</span></label>
        <select class="dsb-input" id="dsb-premade-extra" required><option value="">${esc(t('-- Select --'))}</option>` +
        extraDef.options.map((o) => `<option value="${esc(o.label)}" data-cost="${o.cost || 0}">${esc(tl(o.label))}${o.cost > 0 ? ` (+$${Number(o.cost).toFixed(2)})` : ''}</option>`).join('') + `</select></div>`;
    }
    html += `</div>`;
    box.innerHTML = html;
    const ex = $('#dsb-premade-extra');
    if (ex) ex.onchange = () => {
      state.premadeExtras = ex.value ? [{label: extraDef.label, value: ex.value, cost: parseFloat(ex.selectedOptions[0].dataset.cost || 0)}] : [];
      computePrice();
    };
    $('#dsb-premade-a').oninput = (e) => { state.premadeA = e.target.value; updateCrumbs(); };
    const bEl = $('#dsb-premade-b'); if (bEl) bEl.oninput = (e) => { state.premadeB = e.target.value; };
  }

  // ---------- review ----------
  function isDone() {
    if (state.buildMode === 'premade') return !!state.premadeProduct;
    return !!(state.dutyKey && state.seriesKey && state.frontAppKey && state.rearAppKey && measureValid());
  }
  function enterReview() { renderReview(); showStep(6); markReady(); }
  function markReady() {
    const done = isDone();
    $('#dsb-configured').value = done ? '1' : '0';
    const st = $('.dsb-cart-status');
    st.classList.toggle('ready', done);
    st.querySelector('span').textContent = done ? I18N.readyToAdd : I18N.completeAll;
    $('[data-add-btn]').disabled = !done;
    updateCrumbs();
  }
  function renderReview() {
    const set = (f, v) => { const el = $(`[data-field="${f}"]`); if (el) { el.textContent = v || '—'; el.closest('.dsb-review-row').hidden = !v; } };
    set('duty-full', tl(state.dutyLabel));
    set('series-full', tl(state.seriesLabel));
    set('material-full', state.buildMode === 'custom' && state.materialKey ? (state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel')) : '');
    set('premade-name', state.premadeProduct?.name || '');
    set('premade-measure', state.premadeProduct ? [state.premadeA ? `A: ${state.premadeA}"` : '', state.premadeB ? `B: ${state.premadeB}"` : ''].filter(Boolean).join(' • ') : '');
    set('front-app-full', state.buildMode === 'custom' ? tl(state.frontAppLabel) : '');
    set('rear-app-full', state.buildMode === 'custom' ? tl(state.rearAppLabel) : '');
    const specs = [];
    if (state.extras.a1) specs.push(`A1: ${state.extras.a1}"`);
    if (state.extras.c1) specs.push(`C1: ${state.extras.c1}"`);
    if (state.tubeOd) specs.push(t('Tube :od', {od: state.tubeOd}));
    set('extras-full', state.buildMode === 'custom' ? specs.join(' • ') : (state.premadeExtras.map((e) => `${tl(e.label)} ${tl(e.value)}`).join(' • ')));
    set('flange-full', state.flangeAddon.label ? `${state.flangeAddon.label} (+${money(state.flangeAddon.price)})` : '');
    set('trans-full', state.transAddon.label ? `${state.transAddon.label} (+${money(state.transAddon.price)})` : '');
    // diagram
    const dImg = $('#dsb-review-diagram-img');
    dImg.src = state.buildMode === 'premade' ? (state.premadeProduct?.has_ab ? CFG.diagramABUrl : CFG.diagramAUrl) : currentDiagram();
    const rPlan = state.buildMode === 'custom' && planFor(state.frontAppKey, state.rearAppKey), rBox = $('#dsb-review-plan');
    rBox.hidden = !rPlan; dImg.hidden = !!rPlan; $('#dsb-review-diagram-badges').hidden = !!rPlan;
    if (rPlan) renderPlan(rBox, rPlan, false).catch(() => { rBox.hidden = true; dImg.hidden = false; $('#dsb-review-diagram-badges').hidden = false; });
    const badges = $('#dsb-review-diagram-badges'); badges.innerHTML = '';
    const pairs = state.buildMode === 'premade'
      ? [['A', state.premadeA], ['B', state.premadeB]]
      : [['A1', state.extras.a1], ['C1', state.extras.c1], [t('Tube OD'), state.tubeOd]];
    pairs.filter(([, v]) => v).forEach(([k, v]) => {
      const d = document.createElement('span'); d.className = 'dsb-diagram-badge';
      d.innerHTML = `<b>${esc(k)}</b> ${esc(v)}${/^[A-Z]\d?$/.test(k) ? '"' : ''}`;
      badges.appendChild(d);
    });
    // tier picker: hidden for premade + aluminum
    $('.dsb-tier-picker').hidden = state.buildMode === 'premade' || state.materialKey === 'aluminum';
    if (state.materialKey === 'aluminum') selectTier('new', true);
    // share
    $('[data-share-url]').value = buildShareUrl();
    renderPrice();
  }
  function selectTier(tier, silent) {
    state.priceTier = tier;
    $$('.dsb-tier-card').forEach((c) => c.classList.toggle('dsb-tier-card--selected', c.dataset.selectTier === tier));
    if (!silent) computePrice(); else renderPrice();
    updateHidden();
  }
  function buildShareUrl() {
    const p = new URLSearchParams();
    const map = {dsb_duty: state.dutyKey, dsb_subcat: state.subcatKey, dsb_series: state.seriesKey, dsb_front: state.frontAppKey, dsb_rear: state.rearAppKey, dsb_a1: state.extras.a1, dsb_c1: state.extras.c1, dsb_notes: state.extras.notes, dsb_flange_label: state.flangeAddon.label, dsb_flange_price: state.flangeAddon.price || '', dsb_flange_value: state.flangeAddon.value, dsb_trans_label: state.transAddon.label, dsb_trans_price: state.transAddon.price || '', dsb_trans_value: state.transAddon.value};
    if (state.priceTier === 'new') map.dsb_tier = 'new';
    if (state.materialKey) map.dsb_material = state.materialKey;
    Object.entries(map).forEach(([k, v]) => { if (v) p.set(k, v); });
    return location.origin + location.pathname + '?' + p.toString();
  }
  function applyUrlParams() {
    const p = new URLSearchParams(location.search);
    if (!p.has('dsb_duty')) return;
    state.buildMode = 'custom'; state.buildModeChosen = true;
    const duty = p.get('dsb_duty'); const d = CFG.tree[duty]; if (!d) return;
    state.dutyKey = duty; state.dutyLabel = d.label;
    state.subcatKey = p.get('dsb_subcat') || null;
    state.subcatLabel = state.subcatKey ? d.subcategories?.[state.subcatKey]?.label : null;
    const sk = p.get('dsb_series'); const s = seriesMap()[sk];
    if (s) { state.seriesKey = sk; state.seriesLabel = s.label; }
    state.materialKey = p.get('dsb_material') || (isAluminumEligible() ? null : 'steel');
    const fk = p.get('dsb_front'); if (fk && CFG.frontApps[fk]) { state.frontAppKey = fk; state.frontAppLabel = CFG.frontApps[fk].label; }
    const rk = p.get('dsb_rear'); if (rk && CFG.rearApps[rk]) { state.rearAppKey = rk; state.rearAppLabel = CFG.rearApps[rk].label; }
    state.extras.a1 = p.get('dsb_a1') || ''; state.extras.c1 = p.get('dsb_c1') || ''; state.extras.notes = p.get('dsb_notes') || '';
    if (p.get('dsb_flange_label')) state.flangeAddon = {label: p.get('dsb_flange_label'), price: parseFloat(p.get('dsb_flange_price') || 0), value: p.get('dsb_flange_value') || ''};
    if (p.get('dsb_trans_label')) state.transAddon = {label: p.get('dsb_trans_label'), price: parseFloat(p.get('dsb_trans_price') || 0), value: p.get('dsb_trans_value') || ''};
    if (p.get('dsb_tier') === 'new') state.priceTier = 'new';
    $('#dsb-inp-a1').value = state.extras.a1; $('#dsb-inp-c1').value = state.extras.c1;
    $('#dsb-notes').value = state.extras.notes;
    renderSeries(); renderApps('front'); renderApps('rear'); setupMeasure(); computePrice();
    if (isDone()) enterReview(); else showStep(1);
  }

  // ---------- hidden inputs (same names as original) ----------
  function updateHidden() {
    const H = (n, v) => { const el = $(`[name="${n}"]`); if (el) el.value = v ?? ''; };
    H('dsb_duty_key', state.dutyKey); H('dsb_duty_label', state.dutyLabel);
    H('dsb_series_key', state.seriesKey); H('dsb_series_label', state.seriesLabel);
    H('dsb_material', state.materialKey || '');
    H('dsb_front_app', state.buildMode === 'premade' ? '_premade' : (state.frontAppLabel || ''));
    H('dsb_rear_app', state.buildMode === 'premade' ? '_premade' : (state.rearAppLabel || ''));
    H('dsb_final_price', state.price || '');
    H('dsb_price_tier', state.priceTier);
    H('dsb_flange_addon_label', state.flangeAddon.label); H('dsb_flange_addon_price', state.flangeAddon.price || 0);
    H('dsb_trans_addon_label', state.transAddon.label); H('dsb_trans_addon_price', state.transAddon.price || 0);
    H('dsb_premade_extras', JSON.stringify(state.premadeExtras));
  }

  // ---------- submit ----------
  let submitting = false;
  function submit() {
    if (submitting) return;
    if ($('#dsb-configured').value !== '1') { toast(I18N.pleaseSelect); wrap.scrollIntoView({behavior: 'smooth'}); return; }
    submitting = true;
    const btn = $('[data-add-btn]'); btn.disabled = true; btn.classList.add('dsb-loading');
    const body = new URLSearchParams({
      pieces: CFG.pieces, build_mode: state.buildMode,
      premade_product_id: state.premadeProduct?.id || 0,
      premade_a: state.premadeA || '', premade_b: state.premadeB || '',
      premade_extras: JSON.stringify(state.premadeExtras),
      duty_key: state.dutyKey || '', duty_label: state.dutyLabel || '',
      subcat_key: state.subcatKey || '', series_key: state.seriesKey || '', series_label: state.seriesLabel || '',
      material: state.materialKey || 'steel', price_tier: state.priceTier,
      front_app: state.frontAppKey || '', rear_app: state.rearAppKey || '',
      length: '', a1: state.extras.a1 || 0, c1: state.extras.c1 || 0, b1: 0, // length: legacy hidden field — always empty on live (tube rules inert)
      flange_yoke_kind: state.flangeAddon.value ? 'new' : 'none',
      flange_yoke_product_id: state.flangeAddon.product_id || 0,
      trans_yoke_kind: state.transAddon.value ? 'new' : 'none',
      trans_yoke_product_id: state.transAddon.product_id || 0,
      notes: $('#dsb-notes').value || '',
      quantity: $('.dsb-qty-input').value || 1,
    });
    fetch(CFG.endpoints.submit, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'}, body})
      .then((r) => r.json()).then((res) => {
        submitting = false; btn.classList.remove('dsb-loading');
        if (!res.success) { btn.disabled = false; btn.classList.add('dsb-error'); setTimeout(() => btn.classList.remove('dsb-error'), 1800); toast((res.errors || [t('Error')]).join(' ')); return; }
        btn.classList.add('dsb-success');
        toast(t('Added to cart!'));
        // update header cart badge
        const badge = document.querySelector('[data-cart-count]');
        if (badge) { badge.textContent = res.data.cart_count; badge.hidden = false; }
        // confirmation panel with link to cart
        $('#dsb-confirm-price').textContent = money(res.data.price.final);
        $('#dsb-confirm-summary').textContent = res.data.summary;
        $$('.dsb-step').forEach((s) => s.classList.remove('dsb-step--active'));
        $('#dsb-confirm').hidden = false;
        $('.dsb-cart-footer').hidden = true;
      }).catch(() => { submitting = false; btn.disabled = false; btn.classList.remove('dsb-loading'); toast(I18N.networkError); });
  }

  // ---------- bind ----------
  function bind() {
    $$('.dsb-crumb').forEach((c) => c.onclick = () => { const t = +c.dataset.goto; if (c.classList.contains('completed') || t === state.step) showStep(t); });
    $$('[data-back]').forEach((b) => b.onclick = () => goBack(+b.dataset.back));
    $$('[data-back-auto]').forEach((b) => b.onclick = goPrev);
    $$('.dsb-mode-card[data-mode]').forEach((b) => b.onclick = () => {
      state.buildMode = b.dataset.mode; state.buildModeChosen = true; state.premadeProduct = null;
      $$('.dsb-mode-card[data-mode]').forEach((c) => c.classList.toggle('dsb-mode-card--selected', c === b));
      updateCrumbs(); showStep(1);
    });
    $$('.dsb-mode-card[data-material]').forEach((b) => b.onclick = () => {
      state.materialKey = b.dataset.material;
      if (state.materialKey === 'aluminum') state.priceTier = 'new';
      $$('.dsb-mode-card[data-material]').forEach((c) => c.classList.toggle('dsb-mode-card--selected', c === b));
      computePrice(); renderApps('front'); showStep(14);
    });
    const search = $('#dsb-series-search');
    search.oninput = () => { state.search = search.value; $('.dsb-search-clear').hidden = !search.value; renderSeries(); };
    $('.dsb-search-clear').onclick = () => { search.value = ''; state.search = ''; renderSeries(); };
    ['a1', 'c1'].forEach((f) => {
      $(`#dsb-inp-${f}`).oninput = (e) => {
        state.extras[f] = e.target.value;
        updateMaterialWarning(); computePrice(); updateHidden(); updateCrumbs(); drawOverlay();
      };
    });
    $('[data-next="measure"]').onclick = () => { if (!measureValid()) { toast(I18N.required); return; } afterMeasure(); };
    $$('[data-yoke-answer]').forEach((b) => b.onclick = () => {
      const [which, ans] = b.dataset.yokeAnswer.split(':');
      if (which === 'flange') { if (ans === 'yes') { state.flangeChoice = 'yes'; cat.page.flange = 1; loadCatalog('flange'); } else { state.flangeChoice = 'no'; state.flangeAddon = {label: '', price: 0, value: ''}; computePrice(); afterFrontYoke(); } }
      else { if (ans === 'yes') { state.transChoice = 'yes'; cat.page.trans = 1; loadCatalog('trans'); } else { state.transChoice = 'no'; state.transAddon = {label: '', price: 0, value: ''}; computePrice(); afterRearYoke(); } }
    });
    $('#dsb-premade-length-filter').onchange = (e) => { pm.length = e.target.value; pm.page = 1; loadPremades(); };
    $('[data-next="epo"]').onclick = () => {
      const req = $('#dsb-premade-extra');
      if (req && !req.value) { toast(I18N.required); req.reportValidity(); return; }
      enterReview();
    };
    $$('.dsb-tier-card').forEach((c) => c.onclick = () => selectTier(c.dataset.selectTier));
    $('#dsb-notes').oninput = (e) => { state.extras.notes = e.target.value; };
    $('[data-action="copy"]').onclick = () => {
      const inp = $('[data-share-url]'); inp.value = buildShareUrl();
      (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).then(() => toast(I18N.copied)).catch(() => { inp.select(); document.execCommand('copy'); toast(I18N.copied); });
    };
    $('[data-action="print"]').onclick = () => {
      document.body.classList.add('dsb-printing');
      requestAnimationFrame(() => requestAnimationFrame(() => { window.print(); }));
      const off = () => { document.body.classList.remove('dsb-printing'); window.removeEventListener('afterprint', off); };
      window.addEventListener('afterprint', off); setTimeout(off, 30000);
    };
    $$('[data-qty]').forEach((b) => b.onclick = () => {
      const inp = $('.dsb-qty-input');
      inp.value = Math.max(1, (+inp.value || 1) + (b.dataset.qty === '+' ? 1 : -1));
    });
    $('[data-add-btn]').onclick = submit;
    const planBox = $('#dsb-plan-svg'), planInp = $('#dsb-plan-input');
    planBox.addEventListener('click', (e) => { const tx = e.target.closest('text.dim-text[data-field]'); if (tx) openDimInput(tx); });
    planBox.addEventListener('keydown', (e) => { const tx = e.target.closest('text.dim-text[data-field]'); if (tx && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openDimInput(tx); } });
    planInp.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); commitDim(1); }
      else if (e.key === 'Tab') { e.preventDefault(); commitDim(e.shiftKey ? -1 : 1); }
      else if (e.key === 'Escape') { planInp.hidden = true; $$('text.dim-text.is-editing').forEach((x) => x.classList.remove('is-editing')); }
    });
    // clicking away saves the value; moving to the next dimension re-opens the same input, so only commit
    // if the input is still showing the field it had when it lost focus
    planInp.addEventListener('blur', () => { const f = planInp.dataset.field; setTimeout(() => { if (!planInp.hidden && planInp.dataset.field === f && document.activeElement !== planInp) commitDim(0); }, 150); });
    ['a1', 'c1'].forEach((f) => $(`#dsb-inp-${f}`).addEventListener('input', () => { paintPlan(planBox); renderSheet(); }));
    $$('[data-mbar-go]').forEach((b) => b.onclick = () => $('#dsb-sheet').scrollIntoView({behavior: 'smooth', block: 'start'}));
    // the mobile price bar steps aside while the build sheet itself is on screen
    const mbar = document.querySelector('.dsb-mbar');
    if (mbar && 'IntersectionObserver' in window) new IntersectionObserver((en) => mbar.classList.toggle('is-off', en[0].isIntersecting), {threshold: 0.15}).observe($('#dsb-sheet'));
    $('#dsb-diagram-img').onload = drawOverlay;
    window.addEventListener('resize', drawOverlay);
  }

  renderDutyCards(); bind(); computePrice(); updateCrumbs(); applyUrlParams();
})();
