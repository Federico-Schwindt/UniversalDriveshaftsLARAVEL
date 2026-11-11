/* UDS online driveshaft builder (Nov 2026 rebuild).
   Question tree from storage/app/builder-tree.json (window.dsbConfig.flow), then series → material → rear end →
   rear yoke / hardware → measurements → front yoke → review. The server (/dsb/quote, /dsb/submit) recomputes every
   price; the numbers computed here are a display mirror only. State lives in the URL so reload / back / share work. */
(function () {
  'use strict';
  const CFG = window.dsbConfig;
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const wrap = document.getElementById('ubs-dsb');
  if (!wrap || !CFG) return;

  // Display-only translation (window.UDS_T is filled by the page for the visitor's language). Values kept in
  // `state` and sent to the server stay in English: the shop reads them.
  const T = window.UDS_T || {};
  const t = (s, vars) => { let o = T[s] || s; if (vars) Object.entries(vars).forEach(([k, v]) => { o = o.split(':' + k).join(v); }); return o; };
  const tl = (s) => { if (!s) return s; if (T[s]) return T[s]; const m = /^(.+) Series$/.exec(s); return m && T[':x Series'] ? T[':x Series'].split(':x').join(m[1]) : s; };
  const $ = (s, r) => (r || wrap).querySelector(s);
  const $$ = (s, r) => Array.from((r || wrap).querySelectorAll(s));
  const esc = (x) => String(x ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const money = (n) => CFG.currency + Number(n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  // what a length / ratio field holds, as a plain number string: "52,5" from a comma keyboard, "52.5 in", '52.5"' → "52.5"
  const decimal = (v) => { const m = String(v ?? '').replace(',', '.').match(/\d*\.?\d+/); return m ? m[0] : ''; };
  const fmtIn = (v) => (v === '' || v == null || isNaN(+v) ? '' : String(+v) + '"');
  const FLOW = CFG.flow || {nodes: {}, families: {}};
  const NODES = FLOW.nodes || {}, FAMS = FLOW.families || {}, VARIANTS = FLOW.series_variants || {};
  const LM = CFG.tree.light_medium || {series: {}};

  const state = {
    step: 'tree', node: 'start', answers: [], family: null,
    dutyKey: null, subcatKey: null, seriesKey: null, variant: '', materialKey: null,
    front: null, rear: null,
    frontYoke: {kind: '', product: null, model: '', make: ''}, rearYoke: {kind: '', product: null, make: ''}, hardware: {kind: '', product: null},
    a1: '', c1: '', tier: 'certified', notes: '', quote: null, tubeOd: '', speed: {mph: '', ratio: '', tire: ''},
  };
  const ENG = FLOW.engineering || {}, PHOTOS = FLOW.photos || {}, COVER = CFG.amCoverage || {};
  state.amOptions = {};
  // a leaf the supplier price table covers (family + series + material): priced from the table at our markdown
  const isReference = () => !!(state.family && state.seriesKey && ((COVER[state.family] || {})[state.seriesKey.toLowerCase()] || []).includes(state.materialKey || 'steel'));
  const cat = {filters: {front: {}, rear: {}}, page: {front: 1, rear: 1}};
  const makeCache = {};   // series -> {makes: [{key, label, count}], total}: the vehicle makes its flange yokes name
  const products = {}; // id -> product card, cached for restores
  try { Object.assign(products, JSON.parse(sessionStorage.getItem('dsb:products') || '{}')); } catch (e) { /* private mode */ }
  const remember = (p) => { if (!p || !p.id) return; products[p.id] = p; try { sessionStorage.setItem('dsb:products', JSON.stringify(products)); } catch (e) { /* ignore */ } };

  // ---------- helpers ----------
  function toast(msg) {
    let el = $('.dsb-toast');
    if (!el) { el = document.createElement('div'); el.className = 'dsb-toast'; wrap.appendChild(el); }
    el.textContent = msg; el.classList.add('show');
    clearTimeout(el._h); el._h = setTimeout(() => el.classList.remove('show'), 2800);
  }
  // the family a leaf resolves to: a tree family, a heavy-duty subcategory ("HD:hd_spl") or a legacy saved design
  function fam() {
    const id = state.family; if (!id) return null;
    if (FAMS[id]) return FAMS[id];
    if (id.startsWith('HD:')) {
      const sc = (CFG.tree.heavy && CFG.tree.heavy.subcategories || {})[id.slice(3)];
      return sc ? {label: sc.label, series: Object.keys(sc.series || {}), front_app: null, yoke_choice: true, hd: true, subcat: id.slice(3), models: [], note: ''} : null;
    }
    if (id === 'LEGACY') return {label: '', series: Object.keys(LM.series || {}), front_app: null, yoke_choice: true, models: [], note: ''};
    return null;
  }
  const yokeTypeFor = (app) => ({flange: 'flange', slip_flange: 'flange', trans: 'trans', slip_no_flange: 'trans'})[app] || null;
  const seriesNode = () => { const f = fam(); if (!f) return null; const map = f.hd ? ((CFG.tree.heavy.subcategories[f.subcat] || {}).series || {}) : (LM.series || {}); return map[state.seriesKey] || null; };
  const seriesLabel = () => { const n = seriesNode(); return n ? n.label : (state.seriesKey || ''); };
  const aluEligible = () => state.dutyKey === 'light_medium' && !!(CFG.aluminumPricing || {})[(state.seriesKey || '').toLowerCase()];
  const usedPrice = (app) => { const m = yokeTypeFor(app) === 'trans' ? CFG.yokes.used_trans : CFG.yokes.used_flange; return parseFloat((m || {})[(state.seriesKey || '').toLowerCase()] || 0); };
  const frontYokeAsk = () => { const f = fam(); return !!(f && f.yoke_choice !== false && yokeTypeFor(state.front)); };
  const maxLen = () => { const m = (CFG.maxLengths || {})[state.seriesKey]; return m ? m[state.materialKey || 'steel'] : null; };
  // a new flange yoke asks the vehicle make first (owner, 2026-11-11), unless no yoke of the series names one (it could only say "see all")
  const brandAsk = (side) => {
    const y = side === 'front' ? state.frontYoke : state.rearYoke, mc = makeCache[state.seriesKey || ''];
    return y.kind === 'new' && yokeTypeFor(side === 'front' ? state.front : state.rear) === 'flange' && !(mc && !mc.makes.length);
  };

  function fixedSteps() {
    const f = fam(); if (!f) return [];
    const s = ['series'];
    if (aluEligible()) s.push('material');
    if (!f.front_app) s.push('front');
    s.push('rear');
    if (yokeTypeFor(state.rear)) { s.push('rear_yoke'); if (state.rearYoke.kind === 'new') { if (brandAsk('rear')) s.push('rear_brand'); s.push('rear_pick'); } }
    const ref = isReference();
    if (state.rear === 'weld' && !ref) s.push('hardware');
    s.push('measure', 'speed');
    // the options step only when the supplier product has something to choose (once the quote says which product it is)
    const rp = refProduct();
    if (ref) { if (!(rp && rp.product && !realOptions(rp.product).length)) s.push('options'); }   // supplier builds never ask the front yoke
    else if (frontYokeAsk()) { s.push('front_yoke'); if (state.frontYoke.kind === 'new') { if (brandAsk('front')) s.push('front_brand'); s.push('front_pick'); } }
    s.push('review');
    return s;
  }
  const PHASES = [['Application', ['tree']], ['Shaft', ['series', 'material']], ['Ends', ['front', 'rear', 'rear_yoke', 'rear_brand', 'rear_pick', 'hardware']],
    ['Length', ['measure', 'speed']], ['Your price', ['options', 'front_yoke', 'front_brand', 'front_pick', 'review']]];
  const STEP_NAMES = {series: 'U-joint series', material: 'Tube material', front: 'Front end', rear: 'Rear end', rear_yoke: 'Rear yoke', rear_brand: 'Vehicle make', rear_pick: 'Pick the yoke', hardware: 'Rear hardware', measure: 'Measurements', speed: 'Speed check', options: 'Build options', front_yoke: 'Front yoke', front_brand: 'Vehicle make', front_pick: 'Pick the yoke', review: 'Your driveshaft'};

  // ---------- pricing mirror (the server is authoritative) ----------
  function odRows() {
    const key = (state.seriesKey || '').toLowerCase();
    return ((state.materialKey === 'aluminum' ? CFG.aluminumPricing : CFG.tubeOdPricing) || {})[key] || [];
  }
  function addonPrice(y, app) {
    if (!y || !y.kind || y.kind === 'none' || y.kind === 'own') return 0;
    if (y.kind === 'used') return usedPrice(app);
    return y.product ? parseFloat(y.product.price || 0) : 0;
  }
  function localPrice() {
    const n = seriesNode(); if (!n) return null;
    let cert = parseFloat(n.price) > 0 ? parseFloat(n.price) : 0, brand = parseFloat(n.new_price) > 0 ? parseFloat(n.new_price) : 0;
    const key = (state.seriesKey || '').toLowerCase();
    if (state.materialKey === 'aluminum' && (CFG.aluminumFlatPricing || {})[key]) {
      const f = CFG.aluminumFlatPricing[key];
      if (parseFloat(f.cert_price) > 0) cert = parseFloat(f.cert_price);
      if (parseFloat(f.new_price) > 0) brand = parseFloat(f.new_price);
    }
    const rows = odRows(); let od = '';
    if (rows.length) {
      const a1 = parseFloat(state.a1) || 0;
      let row = rows.find((r) => !parseFloat(r.max_a1) || parseFloat(r.max_a1) >= a1) || rows[rows.length - 1];
      if (parseFloat(row.cert_price) > 0) cert = parseFloat(row.cert_price);
      if (parseFloat(row.new_price) > 0) brand = parseFloat(row.new_price);
      od = row.od || '';
    }
    const addons = addonPrice(state.frontYoke, state.front) + addonPrice(state.rearYoke, state.rear) + (['strap', 'ubolt'].includes(state.hardware.kind) && state.hardware.product ? parseFloat(state.hardware.product.price || 0) : 0);
    const tier = state.materialKey === 'aluminum' ? 'new' : state.tier;
    return {certified: cert + addons, brand: brand + addons, final: (tier === 'new' ? brand : cert) + addons, od, base: tier === 'new' ? brand : cert};
  }
  // the lowest priced tube of the series in the chosen material: what a build "starts at" before the length is known
  function startsAt() {
    // supplier-priced builds start at the supplier's lowest variant (85 %), the same table the final price comes from
    if (isReference()) { const v = (((CFG.amFrom || {})[state.family] || {})[(state.seriesKey || '').toLowerCase()] || {})[state.materialKey || 'steel']; if (v > 0) return v; }
    const rows = odRows(); const tier = state.materialKey === 'aluminum' ? 'new' : state.tier;
    const vals = rows.map((r) => parseFloat(tier === 'new' ? r.new_price : r.cert_price) || parseFloat(r.new_price) || 0).filter((v) => v > 0);
    if (vals.length) return Math.min(...vals);
    const n = seriesNode(); return n ? (parseFloat(tier === 'new' ? n.new_price : n.price) || 0) : 0;
  }
  let quoteTimer = null, quoteSeq = 0;
  function requestQuote() {
    clearTimeout(quoteTimer);
    if (!state.seriesKey) { state.quote = null; renderPrice(); return; }
    quoteTimer = setTimeout(() => {
      const seq = ++quoteSeq;
      fetch(CFG.endpoints.quote, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload())})
        .then((r) => r.json()).then((res) => { if (seq !== quoteSeq) return; state.quote = res.success ? res.data : null; renderPrice(); if (state.step === 'measure') checkLength(); if (state.step === 'speed') renderSpeed(); if (state.step === 'review') renderReview(); if (state.step === 'options') renderOptions(); if (state.step === 'speed') renderSpeed(); renderSheet(); })
        .catch(() => {});
    }, 300);
  }
  function priceNow() {
    const lp = localPrice(); if (!lp) return null;
    const q = state.quote;
    if (q && q.final > 0) return {final: q.final, certified: isReference() ? q.final : lp.certified, brand: isReference() ? q.final : lp.brand, od: (q.breakdown || {}).tube_od || lp.od, base: q.base, server: true, q};
    if (isReference()) return null; // never show our engine's number for a table-priced leaf
    return lp;
  }
  // a supplier option is a question only when it offers a choice; the long-shaft option is answered from the length
  // "1310" for templates that already say "series" (they read "the 1310 Series series" with the full label)
  const seriesNum = () => String(tl(seriesLabel())).replace(/\s*\b(series|serie|série|系列|シリーズ)\b\s*/i, ' ').trim();
  const isLenOpt = (o) => /\b88\b|or over|length/i.test(o.name);
  const realOptions = (product) => ((product && product.options) || []).filter((o) => (o.values || []).length > 1 && !isLenOpt(o));
  // our own headings for the supplier's option groups
  const optionName = (n) => /hardware|kit/i.test(n) ? t('U-joint hardware kit') : (/build option|slip yoke/i.test(n) ? t('Slip yoke') : (isLenOpt({name: n}) ? t('Long shaft') : (/u-?joint/i.test(n) ? t('U-joints') : tl(n))));
  const shownOption = (k, v) => !/^default title$/i.test(String(v)) && !/^title$/i.test(String(k));
  const refProduct = () => (state.quote && state.quote.breakdown && state.quote.breakdown.pricing === 'reference') ? state.quote.breakdown : null;
  function renderOptions() {
    const box = $('[data-options]'); const bd = refProduct();
    if (!bd || !bd.product) { box.innerHTML = `<div class="dsb-loading">${esc(t('Loading…'))}</div>`; return; }
    const labels = CFG.optionLabels || {}; const chosen = bd.options || {};
    const lengthOpt = (bd.product.options || []).find(isLenOpt);
    box.innerHTML = '';
    const tube = {od: bd.product.od, material: state.materialKey === 'aluminum' ? t('aluminum') : t('steel'), series: bd.product.series};
    $('[data-options-product]').textContent = bd.max_length ? t(':od in :material tube, :series series, up to :max in', Object.assign({max: bd.max_length}, tube)) : t(':od in :material tube, :series series', tube);
    const real = realOptions(bd.product);
    if (!real.length) { box.innerHTML = `<p class="dsb-hint">${esc(t('Nothing else to choose for this build. Continue to your price.'))}</p>`; }
    (bd.product.options || []).filter((o) => real.includes(o) || (o === lengthOpt && real.length)).forEach((o) => {
      const group = document.createElement('div'); group.className = 'dsb-optgroup';
      const isLen = o === lengthOpt;
      group.innerHTML = `<h3>${esc(optionName(o.name))}</h3>` + (isLen ? `<p class="dsb-hint">${esc(t('Answered from your measurement (seal to flange face): :len in', {len: state.a1}))}</p>` : '');
      o.values.forEach((v) => {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card';
        const on = chosen[o.name] === v; if (on) b.classList.add('is-on');
        if (isLen) b.disabled = !on;
        b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(tl(labels[v] || v))}</span></span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
        b.onclick = () => { if (isLen) return; state.amOptions[o.name] = v; $$('.dsb-card', group).forEach((c) => c.classList.toggle('is-on', c === b)); state.quote = null; requestQuote(); syncUrl(false); };
        group.appendChild(b);
      });
      box.appendChild(group);
    });
    const foot = document.createElement('div'); foot.className = 'dsb-step-foot';
    foot.innerHTML = `<button type="button" class="dsb-btn dsb-btn--primary">${esc(t('Continue'))}</button>`;
    foot.firstChild.onclick = () => { state.optionsSeen = true; nextStep(); }; box.appendChild(foot);
  }
  function renderPrice() {
    const p = priceNow(); const hasLen = parseFloat(state.a1) > 0;
    const val = $('[data-sheet-price]'), note = $('[data-sheet-price-note]');
    if (!p || !hasLen) {
      val.textContent = t('Price after measurements'); val.classList.add('is-pending');
      const from = p ? startsAt() : 0;
      note.textContent = from > 0 ? t(':material :series builds start at :price', {material: state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel'), series: tl(seriesLabel()), price: money(from)}) : '';
      $$('[data-mbar-price]').forEach((el) => { el.textContent = from > 0 ? t('from :price', {price: money(from)}) : '—'; });
    } else {
      val.textContent = money(p.final); val.classList.remove('is-pending');
      note.textContent = p.od ? t('Lands on a :od tube', {od: p.od}) : '';
      $$('[data-mbar-price]').forEach((el) => { el.textContent = money(p.final); });
    }
    state.tubeOd = p && hasLen ? p.od : '';
    const tc = $('[data-tier-price="certified"]'), tn = $('[data-tier-price="new"]');
    if (tc) tc.textContent = p ? money(p.certified) : '';
    if (tn) tn.textContent = p ? money(p.brand) : '';
    $('#dsb-review-price').textContent = p && hasLen ? money(p.final) : t('Price after measurements');
    markReady();
  }

  // ---------- progress / navigation ----------
  function progress() {
    // five fixed stages, so the counter never changes its total as answers add or remove questions
    const ph = PHASES.findIndex(([, steps]) => steps.includes(state.step)), stage = ph < 0 ? 0 : ph;
    const inStage = fixedSteps().filter((x) => PHASES[stage][1].includes(x)), k = Math.max(0, inStage.indexOf(state.step));
    $('[data-progress-step]').textContent = t('Step :n of :total', {n: stage + 1, total: PHASES.length});
    const sub = state.step === 'tree' ? '' : t(STEP_NAMES[state.step] || '');
    $('[data-progress-name]').textContent = t(PHASES[stage][0]) + (sub && sub !== t(PHASES[stage][0]) ? ' · ' + sub : '');
    const frac = state.step === 'tree' ? Math.min(.9, state.answers.length / 5) : (k + 1) / (inStage.length + 1);
    $('[data-progress-bar]').style.width = Math.round(100 * (stage + frac) / PHASES.length) + '%';
    const first = state.step === 'tree' && !state.answers.length;
    $('[data-back]').disabled = first;
    const rs = $('[data-restart]'); if (rs) rs.hidden = first;
    // nothing to sum up before the first answer: the dock stays out of the way
    const dock = document.querySelector('.bld-dock'); if (dock) dock.classList.toggle('is-empty', first);
  }
  function showStep(name, opts) {
    opts = opts || {};
    state.step = name;
    if (name === 'series') renderSeries();
    if (name === 'material') renderMaterial();
    if (name === 'front') renderApps('front');
    if (name === 'rear') renderApps('rear');
    if (name === 'rear_yoke') renderYokeChoices('rear');
    if (name === 'front_yoke') renderYokeChoices('front');
    if (name === 'hardware') renderHardware();
    if (name === 'measure') setupMeasure();
    if (name === 'speed') renderSpeed();
    if (name === 'options') renderOptions();
    if (name === 'review') renderReview();
    if (name === 'rear_brand') renderBrands('rear');
    if (name === 'front_brand') renderBrands('front');
    if (name === 'rear_pick') loadCatalog('rear');
    if (name === 'front_pick') loadCatalog('front');
    $$('.dsb-step').forEach((s) => s.classList.toggle('dsb-step--active', s.dataset.step === name));
    $('#dsb-confirm').hidden = true; $('.dsb-cart-footer').hidden = false;
    progress(); renderSheet(); renderPrice();
    if (!opts.silent) syncUrl(true);
    const r = wrap.getBoundingClientRect();
    if (r.top < 0 || r.top > innerHeight * 0.5) wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
  }
  function nextStep() {
    const s = fixedSteps(); const i = s.indexOf(state.step);
    // a step that has just left the list (an answer changed the flow): go on to the first unanswered one, never back to the start
    if (i < 0) { showStep(firstIncomplete()); return; }
    showStep(s[Math.min(i + 1, s.length - 1)]);
  }
  function goBack() {
    if (state.step === 'tree') {
      const a = state.answers.pop(); if (!a) return;
      state.node = a.node; renderTree(); showStep('tree'); return;
    }
    const s = fixedSteps(); const i = s.indexOf(state.step);
    if (i > 0) { showStep(s[i - 1]); return; }
    const a = state.answers.pop(); state.node = a ? a.node : 'start'; renderTree(); showStep('tree');
  }

  // ---------- question tree ----------
  function treeNode(id) {
    if (id === 'heavy') {
      const subs = (CFG.tree.heavy && CFG.tree.heavy.subcategories) || {};
      return {q: (NODES.heavy || {}).q || 'Heavy-duty family', short: (NODES.heavy || {}).short || 'Heavy-duty family', kind: 'tiles', options: Object.entries(subs).map(([k, sc]) => ({label: sc.label, note: sc.hint || '', leaf: 'HD:' + k, art: '02'}))};
    }
    return NODES[id] || null;
  }
  /* How to count splines: an end view of a slip yoke barrel (27 teeth, two of them blind spots), drawn here so it is
     ours, sharp at any size and translatable; the steps beside it are short and practical. */
  function splineGuide() {
    const n = 27, blind = [6, 20], cx = 110, cy = 110, rIn = 58, rOut = 70;
    let teeth = '', marks = '';
    for (let i = 0; i < n; i++) {
      const a0 = (i / n) * Math.PI * 2 - Math.PI / 2, a1 = a0 + (Math.PI * 2 / n) * 0.55;
      const p = (r, a) => (cx + r * Math.cos(a)).toFixed(1) + ',' + (cy + r * Math.sin(a)).toFixed(1);
      if (blind.includes(i)) {
        teeth += `<path class="sg-blind" d="M${p(rOut, a0)} A${rOut},${rOut} 0 0,1 ${p(rOut, a1)} L${p(rIn - 2, a1)} A${rIn - 2},${rIn - 2} 0 0,0 ${p(rIn - 2, a0)} Z"/>`;
      } else {
        teeth += `<path class="sg-tooth" d="M${p(rOut, a0)} L${p(rIn, a0 + 0.04)} L${p(rIn, a1 - 0.04)} L${p(rOut, a1)} Z"/>`;
      }
      if (i < 3) { const am = (a0 + a1) / 2; marks += `<text class="sg-num" x="${(cx + (rIn - 14) * Math.cos(am)).toFixed(1)}" y="${(cy + (rIn - 14) * Math.sin(am) + 3.5).toFixed(1)}">${i + 1}</text>`; }
    }
    const svg = `<svg class="sg-svg" viewBox="0 0 220 220" role="img" aria-label="${esc(t('End view of a slip yoke: count every tooth, the two wide gaps count as one each'))}">
      <circle class="sg-barrel" cx="${cx}" cy="${cy}" r="96"/><circle class="sg-root" cx="${cx}" cy="${cy}" r="${rOut}"/>${teeth}${marks}
      <path class="sg-arrow" d="M${cx + 30},${cy - 82} A86,86 0 0,1 ${cx + 72},${cy - 46}" marker-end="url(#sg-head)"/>
      <defs><marker id="sg-head" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0,0 L10,5 L0,10 z" class="sg-arrowhead"/></marker></defs></svg>`;
    return `<div class="sg">${svg}<div class="sg-text"><h3>${esc(t('How to count the splines'))}</h3><ol>
      <li>${esc(t('Look into the open end of your old slip yoke, or at the output shaft it slides onto.'))}</li>
      <li>${esc(t('Put a marker dot on one tooth and count every tooth all the way around back to it.'))}</li>
      <li>${esc(t('Some yokes have one or two wide gaps (blind spots) that index them on the shaft. Count each gap as one tooth.'))}</li>
      <li>${esc(t('Not sure? Send us a photo of the end and we will count it with you.'))} <a href="https://wa.me/18136445707" target="_blank" rel="noopener">WhatsApp</a> · <a href="tel:18135246491">(813) 524-6491</a></li>
    </ol><p class="sg-key"><span class="sg-key-blind"></span>${esc(t('Blind spot: counts as one'))}</p></div></div>`;
  }

  function renderTree() {
    const node = treeNode(state.node); const box = $('[data-tree-options]'); box.innerHTML = '';
    if (!node) { state.node = 'start'; return renderTree(); }
    $('[data-tree-title]').textContent = tl(node.q);
    const help = $('[data-tree-help]');
    // spline-count questions get the counting guide (our own drawing and words) instead of a line of help text
    const guide = $('[data-tree-guide]'), counting = /spline/i.test(node.q) && node.options.some((o) => /^\d/.test(o.label));
    if (guide) { guide.hidden = !counting; guide.innerHTML = counting ? splineGuide() : ''; }
    help.hidden = !node.help || counting; help.textContent = node.help ? tl(node.help) : '';
    box.className = 'dsb-tree' + (node.kind === 'list' ? ' dsb-tree--list' : ' dsb-tree--tiles');
    let search = null;
    if (node.kind === 'list' && node.options.length > 14) {
      search = document.createElement('input'); search.type = 'search'; search.className = 'dsb-input dsb-tree-search'; search.placeholder = t('Type to filter'); search.setAttribute('aria-label', t('Type to filter'));
      box.appendChild(search);
    }
    const list = document.createElement('div'); list.className = 'dsb-tree-list'; box.appendChild(list);
    let lastGroup = null;
    node.options.forEach((o, idx) => {
      if (o.group && o.group !== lastGroup) { lastGroup = o.group; const h = document.createElement('h3'); h.className = 'dsb-tree-group'; h.textContent = tl(o.group); list.appendChild(h); }
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-opt' + (node.kind === 'list' ? ' dsb-opt--row' : ' dsb-opt--tile'); b.dataset.idx = idx;
      const picked = state.answers.find((a) => a.node === state.node);
      if (picked && picked.idx === idx) b.classList.add('is-on');
      b.innerHTML = `${o.art ? '<span class="dsb-opt-art"></span>' : (o.image ? `<span class="dsb-opt-img"><img src="${esc(o.image)}" alt="" loading="lazy"></span>` : '')}<span class="dsb-opt-body"><span class="dsb-opt-label">${esc(tl(o.label))}</span>${o.note ? `<span class="dsb-opt-note">${esc(tl(o.note))}</span>` : ''}</span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      if (o.art) b.firstChild.appendChild(sectionArt(o.art, tl(o.label)));
      b.onclick = () => answer(idx);
      list.appendChild(b);
    });
    if (search) search.oninput = () => { const q = search.value.trim().toLowerCase(); $$('.dsb-opt', list).forEach((b) => { b.hidden = q && !b.textContent.toLowerCase().includes(q); }); $$('.dsb-tree-group', list).forEach((h) => { h.hidden = !!q; }); };
  }
  function answer(idx) {
    const node = treeNode(state.node); const o = node && node.options[idx]; if (!o) return;
    state.answers.push({node: state.node, idx, q: node.short || node.q, a: o.label, set: o.set || null});
    if (o.next) { state.node = o.next; renderTree(); showStep('tree'); return; }
    applyFamily(o.leaf); state.node = null; showStep('series');
  }
  function applyFamily(id) {
    const changed = state.family !== id;
    state.family = id; const f = fam(); if (!f) { state.family = null; return; }
    state.dutyKey = f.hd ? 'heavy' : 'light_medium'; state.subcatKey = f.hd ? f.subcat : null;
    // a two-piece shaft carries its slip joint in the rear section, so its front is a plain weld or flange yoke
    const frontFor = (app) => CFG.pieces === 'two' ? ({slip_no_flange: 'weld', slip_flange: 'flange'}[app] || app) : app;
    if (changed) {
      Object.assign(state, {seriesKey: null, variant: '', materialKey: null, front: f.front_app ? frontFor(f.front_app) : null, rear: null, tubeOd: '', quote: null});
      state.frontYoke = {kind: f.yoke_choice === false ? (id === 'REUSE' ? 'own' : 'none') : '', product: null, model: ''};
      state.rearYoke = {kind: '', product: null}; state.hardware = {kind: '', product: null};
    } else if (f.front_app) state.front = frontFor(f.front_app);
  }
  // the structured facts the answers carried (make, model, spline…), for the yoke filter and the review
  function facts() { const o = {}; state.answers.forEach((a) => { if (a.set) Object.assign(o, a.set); }); const f = fam() || {}; return Object.assign({spline: f.spline || '', barrel: f.barrel || '', make: f.make || ''}, o); }

  // ---------- series / material / ends ----------
  function seriesOptions() {
    const f = fam(); if (!f) return [];
    const map = f.hd ? ((CFG.tree.heavy.subcategories[f.subcat] || {}).series || {}) : (LM.series || {});
    const out = [];
    (f.series || []).forEach((k) => { if (!map[k]) return; out.push({key: k, variant: '', label: map[k].label}); if (VARIANTS[k] && !f.hd) out.push({key: k, variant: VARIANTS[k], label: map[k].label}); });
    return out;
  }
  function renderSeries() {
    const box = $('#dsb-series-cards'); box.innerHTML = '';
    const f = fam() || {}; const sug = f.suggest || {};
    seriesOptions().forEach((o) => {
      const spec = (CFG.seriesSpecs || {})[o.key.toLowerCase()] || {};
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card';
      if (state.seriesKey === o.key && state.variant === o.variant) b.classList.add('is-on');
      const ref = spec.cap ? (spec.width ? t('cap :cap × :width wide', {cap: spec.cap, width: spec.width}) : t('cap :cap', {cap: spec.cap})) : '';
      const cls = (ENG.series_class || {})[o.key];
      const vtag = o.variant ? String(o.variant).replace(o.key, '').trim() : '';
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(tl(o.label))}${vtag ? ` <span class="dsb-tag dsb-tag--plain">${esc(t(':make yokes', {make: vtag}))}</span>` : ''}${sug.series === o.key && !o.variant ? `<span class="dsb-tag">${esc(t('Typical for this vehicle'))}</span>` : ''}</span>
        ${cls ? `<span class="dsb-card-desc">${esc(tl(cls))}</span>` : ''}${ref ? `<span class="dsb-card-mono">${esc(ref)}</span>` : ''}</span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      b.onclick = () => {
        const changed = state.seriesKey !== o.key;
        state.seriesKey = o.key; state.variant = o.variant;
        if (changed) { state.quote = null; if (state.frontYoke.kind === 'new') state.frontYoke = {kind: '', product: null, model: state.frontYoke.model}; if (state.rearYoke.kind === 'new') state.rearYoke = {kind: '', product: null}; if (['strap', 'ubolt'].includes(state.hardware.kind)) state.hardware = {kind: '', product: null}; }
        if (!aluEligible()) state.materialKey = 'steel'; else if (!state.materialKey && sug.material) state.materialKey = sug.material;
        if (state.materialKey === 'aluminum' && !aluEligible()) state.materialKey = 'steel';
        requestQuote(); nextStep();
      };
      box.appendChild(b);
    });
  }
  function renderMaterial() {
    const box = $('#dsb-material-cards'); box.innerHTML = '';
    const lens = (CFG.maxLengths || {})[state.seriesKey] || {};
    const f = fam() || {}; const sug = (f.suggest || {}).material;
    [['steel', t('Steel'), t('Standard, strong and economical')], ['aluminum', t('Aluminum'), t('Lighter, longer builds, new components only')]].forEach(([k, label, desc]) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card dsb-card--big';
      if (state.materialKey === k) b.classList.add('is-on');
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(label)}${sug === k ? `<span class="dsb-tag">${esc(t('Typical for this vehicle'))}</span>` : ''}</span><span class="dsb-card-desc">${esc(desc)}</span>${lens[k] ? `<span class="dsb-card-mono">${esc(t('up to :max in', {max: lens[k]}))}</span>` : ''}</span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      b.onclick = () => { state.materialKey = k; if (k === 'aluminum') state.tier = 'new'; if (k === 'steel' && ['slip_flange', 'slip_no_flange'].includes(state.rear)) state.rear = null; state.quote = null; requestQuote(); nextStep(); };
      box.appendChild(b);
    });
  }
  function rearApps() {
    const alu = state.materialKey === 'aluminum', front = state.front;
    const hideCarriers = CFG.pieces !== 'two' || alu || ['slip_flange', 'slip_no_flange', 'trans', 'yoke_shaft'].includes(front);
    return Object.entries(CFG.rearApps).filter(([key, a]) => {
      if (a.aluminum_only) return alu && ['weld', 'flange'].includes(front);
      if (key.startsWith('carrier') && hideCarriers) return false;
      return true;
    });
  }
  function renderApps(which) {
    const box = $(which === 'front' ? '#dsb-front-app-cards' : '#dsb-rear-app-cards'); box.innerHTML = '';
    const entries = which === 'front' ? Object.entries(CFG.frontApps).filter(([, a]) => !(a.lm_only && state.dutyKey !== 'light_medium')) : rearApps();
    const help = (CFG.appHelp || {})[which] || {};
    entries.forEach(([key, a]) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-app-card';
      if ((which === 'front' ? state.front : state.rear) === key) b.classList.add('is-on');
      const code = SECTION[which][key];
      // our own drawing of the end (owner, 2026-11-10: drawings here, not photos)
      const img = a.image_clean || a.image;
      b.innerHTML = `${code ? '<span class="dsb-app-card-img dsb-app-card-img--line"></span>' : (img ? `<span class="dsb-app-card-img"><img src="${esc(img)}" alt="" loading="lazy"></span>` : '')}<span class="dsb-app-card-label">${esc(tl(a.label))}</span>${help[key] ? `<span class="dsb-app-card-desc">${esc(tl(help[key].description))}</span>` : ''}`;
      if (code) b.firstChild.appendChild(sectionArt(code, tl(a.label)));
      b.onclick = () => {
        if (which === 'front') { if (state.front !== key) { state.front = key; state.frontYoke = {kind: '', product: null, model: '', make: ''}; state.rear = null; } }
        else if (state.rear !== key) { state.rear = key; state.rearYoke = {kind: '', product: null, make: ''}; state.hardware = {kind: '', product: null}; }
        state.quote = null; requestQuote(); nextStep();
      };
      box.appendChild(b);
    });
  }
  function renderYokeChoices(side) {
    const box = $(`[data-yoke-choices="${side}"]`); box.innerHTML = '';
    const app = side === 'front' ? state.front : state.rear; const type = yokeTypeFor(app); const y = side === 'front' ? state.frontYoke : state.rearYoke;
    const used = usedPrice(app); const fx = facts();
    if (side === 'front') {
      $('#dsb-front-yoke-title').textContent = type === 'trans' ? t('Slip yoke for this shaft') : t('Flange yoke for this end');
      const bits = [fx.make, fx.model, fx.spline ? t(':n splines', {n: fx.spline}) : '', fx.barrel ? t(':od barrel', {od: fx.barrel}) : ''].filter(Boolean);
      $('#dsb-front-yoke-desc').textContent = bits.length ? t('From your answers: :what', {what: bits.join(', ')}) : t('We balance the shaft with its yokes fitted, so tell us which one goes on.');
      const mw = $('#dsb-front-model-wrap'); mw.hidden = type !== 'trans'; $('#dsb-front-model').value = y.model || '';
    }
    const opts = [
      {kind: 'new', title: t('New yoke'), desc: t('Pick one from our catalog'), price: ''},
      {kind: 'used', title: t('Good used yoke from our shop'), desc: t('Inspected, cleaned and fitted here'), price: used > 0 ? '+' + money(used) : ''},
      {kind: 'own', title: t('Reuse mine'), desc: t('I will send it in or bring it to the shop'), price: money(0)},
    ].filter((o) => o.kind !== 'used' || (used > 0 && !(side === 'front' && isReference())));
    opts.forEach((o) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card dsb-card--big';
      if (o.kind === 'new') { b.dataset.newYoke = side; checkYokeStock(side, type, b); }
      if (y.kind === o.kind) b.classList.add('is-on');
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(o.title)}</span><span class="dsb-card-desc">${esc(o.desc)}</span></span>${o.price ? `<span class="dsb-card-price">${esc(o.price)}</span>` : ''}<span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      b.onclick = () => {
        if (side === 'front') { state.frontYoke.kind = o.kind; if (o.kind !== 'new') state.frontYoke.product = null; state.frontYoke.model = $('#dsb-front-model').value.trim(); }
        else { state.rearYoke.kind = o.kind; if (o.kind !== 'new') state.rearYoke.product = null; }
        state.quote = null; requestQuote(); nextStep();
      };
      box.appendChild(b);
    });
  }
  // how many catalog yokes fit (series, and the spline count for a slip yoke); none: the New choice is switched off with the reason
  function checkYokeStock(side, type, btn) {
    const fx = facts(); const params = new URLSearchParams({type: type || 'flange', series: state.seriesKey || '', page: 1, per_page: 1, filters: '{}'});
    if (type === 'trans' && fx.spline) params.set('spline', fx.spline);
    fetch(CFG.endpoints.yokes + '?' + params).then((r) => r.json()).then(({data}) => {
      if (data && (type || 'flange') === 'flange') makeCache[state.seriesKey || ''] = {makes: data.makes || [], total: data.series_total || 0};
      if (data && data.products && data.products.length) return;
      btn.disabled = true; btn.classList.add('is-off');
      const d = btn.querySelector('.dsb-card-desc');
      if (d) d.textContent = type === 'trans' && fx.spline ? t('None listed online for :series with :n splines. Reuse yours, or call us and we source one.', {series: tl(seriesLabel()), n: fx.spline}) : t('None listed online for :series. Reuse yours, or call us and we source one.', {series: tl(seriesLabel())});
    }).catch(() => {});
  }
  // the vehicle makes the series' flange yokes name, with counts (cached per series)
  function yokeMakes() {
    const key = state.seriesKey || '';
    if (makeCache[key]) return Promise.resolve(makeCache[key]);
    return fetch(CFG.endpoints.yokes + '?' + new URLSearchParams({type: 'flange', series: key, page: 1, per_page: 1, filters: '{}'})).then((r) => r.json())
      .then(({data}) => (makeCache[key] = {makes: (data && data.makes) || [], total: (data && data.series_total) || 0}));
  }
  function renderBrands(side) {
    const box = $(`[data-brand-choices="${side}"]`), y = side === 'front' ? state.frontYoke : state.rearYoke, series = state.seriesKey;
    box.innerHTML = `<div class="dsb-loading">${esc(t('Loading…'))}</div>`;
    // a different make starts the yoke list over (filters, page, and the yoke picked for the other make)
    const pick = (key) => { if (y.make !== key) { y.make = key; y.product = null; cat.filters[side] = {}; cat.page[side] = 1; } state.quote = null; requestQuote(); nextStep(); };
    const card = (key, title, desc) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card dsb-card--brand' + (key === 'all' ? ' dsb-card--all' : '');
      if (y.make === key) b.classList.add('is-on');
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(title)}</span>${desc ? `<span class="dsb-card-desc">${esc(desc)}</span>` : ''}</span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      b.onclick = () => pick(key); box.appendChild(b);
    };
    yokeMakes().then(({makes, total}) => {
      if (state.step !== side + '_brand' || state.seriesKey !== series) return;
      box.innerHTML = '';
      // the make the first answers named (a Ford slip yoke family, say) comes first
      const said = String(facts().make || '').toLowerCase();
      const named = (m) => !!said && m.label.split(' / ').some((w) => said.includes(w.toLowerCase()));
      if (!makes.length) { const n = document.createElement('p'); n.className = 'dsb-hint dsb-brand-none'; n.textContent = t('No :series flange yoke names a vehicle make yet.', {series: tl(seriesLabel())}); box.appendChild(n); }
      makes.slice().sort((a, b) => named(b) - named(a)).forEach((m) => card(m.key, tl(m.label), (named(m) ? t('From your answers') + ' · ' : '') + (m.count === 1 ? t('1 yoke listed') : t(':n yokes listed', {n: m.count}))));
      card('all', t('Other make or not sure'), t('See all :n flange yokes in :series', {n: total, series: tl(seriesLabel())}));
    }).catch(() => { box.innerHTML = ''; card('all', t('Other make or not sure'), t('See every flange yoke in :series', {series: tl(seriesLabel())})); });
  }
  function renderHardware() {
    const box = $('[data-hardware-choices]'); box.innerHTML = ''; const list = $('[data-hardware-list]'); list.hidden = true; list.innerHTML = '';
    [{kind: 'strap', title: t('Strap kit'), desc: t('Two straps and four bolts hold the caps')}, {kind: 'ubolt', title: t('U-bolt kit'), desc: t('Two u-bolts with nuts and washers')}, {kind: 'own', title: t('No, I have mine'), desc: t('Nothing added')}].forEach((o) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-card dsb-card--big';
      if (state.hardware.kind === o.kind) b.classList.add('is-on');
      b.innerHTML = `<span class="dsb-card-body"><span class="dsb-card-title">${esc(o.title)}</span><span class="dsb-card-desc">${esc(o.desc)}</span></span><span class="dsb-opt-arrow" aria-hidden="true">›</span>`;
      b.onclick = () => {
        $$('.dsb-card', box).forEach((c) => c.classList.toggle('is-on', c === b));
        if (o.kind === 'own') { state.hardware = {kind: 'own', product: null}; state.quote = null; requestQuote(); nextStep(); return; }
        state.hardware.kind = o.kind; loadHardware(o.kind);
      };
      box.appendChild(b);
    });
    if (['strap', 'ubolt'].includes(state.hardware.kind)) loadHardware(state.hardware.kind);
  }
  function loadHardware(kind) {
    const list = $('[data-hardware-list]'); list.hidden = false; list.innerHTML = `<div class="dsb-loading">${esc(t('Loading…'))}</div>`;
    fetch(CFG.endpoints.yokes + '?' + new URLSearchParams({type: kind, series: state.seriesKey || ''})).then((r) => r.json()).then(({data}) => {
      list.innerHTML = '';
      if (!data.products.length) { list.innerHTML = `<div class="dsb-empty">${esc(t('No kit is listed for this series; we add the right one when we confirm your order.'))}</div>`; return; }
      const h = document.createElement('p'); h.className = 'dsb-hw-title'; h.textContent = data.matched_series ? t('Pick the kit') : t('Kits are listed for every series: the shop confirms the fit.'); list.appendChild(h);
      data.products.forEach((p) => {
        remember(p);
        const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-hw';
        if (state.hardware.product && state.hardware.product.id === p.id) b.classList.add('is-on');
        b.innerHTML = `<span class="dsb-hw-img">${p.image ? `<img src="${esc(p.image)}" alt="" loading="lazy">` : ''}</span><span class="dsb-hw-body"><b>${esc(p.sku)}</b><span>${esc(p.name)}</span>${p.attributes && p.attributes.series ? `<small>${esc(t('Series'))} ${esc(p.attributes.series)}</small>` : ''}</span><span class="dsb-hw-price">${money(p.price)}</span>`;
        const hi = b.querySelector('.dsb-hw-img img'); if (hi) hi.addEventListener('error', () => hi.remove());   // a broken photo leaves the plain box
        b.onclick = () => { state.hardware.product = p; state.quote = null; requestQuote(); nextStep(); };
        list.appendChild(b);
      });
    }).catch(() => { list.innerHTML = `<div class="dsb-empty">${esc(t('Network error. Please try again.'))}</div>`; });
  }

  // ---------- yoke catalog ----------
  function loadCatalog(side) {
    const app = side === 'front' ? state.front : state.rear; const type = yokeTypeFor(app) || 'flange';
    const root = $(`.dsb-catalog[data-catalog-section="${side}"]`);
    const grid = $('[data-catalog-grid]', root), pag = $('[data-catalog-pagination]', root), fl = $('[data-catalog-filters]', root);
    const fx = facts();
    $(`[data-pick-desc="${side}"]`).textContent = (type === 'trans' ? t('Slip yokes in :series', {series: tl(seriesLabel())}) : t('Flange yokes in :series', {series: tl(seriesLabel())})) + (type === 'trans' && fx.spline ? ' · ' + t(':n splines', {n: fx.spline}) : '');
    grid.innerHTML = `<div class="dsb-loading">${esc(t('Loading…'))}</div>`;
    const params = new URLSearchParams({type, series: state.seriesKey || '', page: cat.page[side], per_page: 24, filters: JSON.stringify(cat.filters[side])});
    if (type === 'trans' && fx.spline && !cat.filters[side]['splines-count-dia']) params.set('spline', fx.spline);
    const y = side === 'front' ? state.frontYoke : state.rearYoke, mk = type === 'flange' && y.make && y.make !== 'all' ? y.make : '';
    if (mk) params.set('make', mk);
    let sa = $('[data-seeall]', root);
    if (!sa) { sa = document.createElement('div'); sa.className = 'dsb-seeall'; sa.dataset.seeall = ''; root.insertBefore(sa, fl); }
    sa.hidden = true;
    fetch(CFG.endpoints.yokes + '?' + params).then((r) => r.json()).then(({data}) => {
      fl.innerHTML = '';
      // filtered by make: say so, and keep every other yoke of the series one tap away
      const m = mk && (data.makes || []).find((x) => x.key === mk);
      if (m) {
        $(`[data-pick-desc="${side}"]`).textContent = t('Flange yokes in :series', {series: tl(seriesLabel())}) + ' · ' + tl(m.label);
        sa.hidden = false;
        sa.innerHTML = `<span>${esc(t('Listed for :make: :n of :total.', {make: tl(m.label), n: m.count, total: data.series_total || 0}))}</span><button type="button" class="dsb-btn dsb-btn--ghost">${esc(t('See all :n flange yokes in :series', {n: data.series_total || 0, series: tl(seriesLabel())}))}</button>`;
        sa.querySelector('button').onclick = () => { y.make = 'all'; cat.page[side] = 1; syncUrl(false); loadCatalog(side); };
      }
      Object.entries(data.filters || {}).forEach(([slug, values]) => {
        const label = ({'hub-dia': 'Hub diameter', 'splines-count-dia': 'Splines', 'center-u-joint-to-end': 'U-joint center to end', 'bolt-circle-dia': 'Bolt circle', 'pilot-dia': 'Pilot diameter', 'series': 'Series'})[slug] || slug.replace(/-/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
        const d = document.createElement('div');
        d.innerHTML = `<label>${esc(tl(label))}</label>`;
        const sel = document.createElement('select'); sel.className = 'dsb-input dsb-catalog-select';
        sel.innerHTML = `<option value="">${esc(t('All'))}</option>` + values.map((v) => `<option${cat.filters[side][slug] === v ? ' selected' : ''}>${esc(v)}</option>`).join('');
        sel.onchange = () => { if (sel.value) cat.filters[side][slug] = sel.value; else delete cat.filters[side][slug]; cat.page[side] = 1; loadCatalog(side); };
        d.appendChild(sel); fl.appendChild(d);
      });
      if (!data.products.length) {
        const filtered = Object.keys(cat.filters[side]).length;
        grid.innerHTML = `<div class="dsb-empty dsb-empty--yoke"><p>${esc(filtered ? t('No yoke matches these filters.') : t('We do not list a yoke for this combination online.'))}</p>
          ${filtered ? `<button type="button" class="dsb-btn dsb-btn--ghost" data-clear>${esc(t('Clear filters'))}</button>` : ''}
          <button type="button" class="dsb-card dsb-card--big" data-own><span class="dsb-card-body"><span class="dsb-card-title">${esc(t('Reuse mine'))}</span><span class="dsb-card-desc">${esc(t('The shop confirms the yoke with you before building.'))}</span></span><span class="dsb-opt-arrow" aria-hidden="true">›</span></button>
          <a class="dsb-card dsb-card--big" href="${esc(CFG.phoneHref || 'tel:18135246491')}"><span class="dsb-card-body"><span class="dsb-card-title">${esc(t('Call us to source one'))}</span><span class="dsb-card-desc">(813) 524-6491</span></span><span class="dsb-opt-arrow" aria-hidden="true">›</span></a></div>`;
        const clr = $('[data-clear]', grid); if (clr) clr.onclick = () => { cat.filters[side] = {}; cat.page[side] = 1; loadCatalog(side); };
        $('[data-own]', grid).onclick = () => { if (side === 'front') state.frontYoke = {kind: 'own', product: null, model: state.frontYoke.model, make: ''}; else state.rearYoke = {kind: 'own', product: null, make: ''}; state.quote = null; requestQuote(); nextStep(); };
        pag.innerHTML = ''; return;
      }
      grid.innerHTML = '';
      data.products.forEach((p) => {
        remember(p);
        const b = document.createElement('button'); b.type = 'button'; b.className = 'dsb-catalog-card';
        const cur = side === 'front' ? state.frontYoke.product : state.rearYoke.product; if (cur && cur.id === p.id) b.classList.add('is-on');
        const ttl = (k) => tl(k.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()));
        const specs = Object.entries(p.attributes || {}).slice(0, 4); while (specs.length < 4) specs.push(['', '']);
        let name = String(p.name || ''); const sku = String(p.sku || '');
        if (sku && name.toUpperCase().startsWith(sku.toUpperCase())) name = name.slice(sku.length).replace(/^[\s,|–-]+/, '');
        b.innerHTML = `<span class="dsb-cat-img">${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.sku || '')}" loading="lazy" decoding="async">` : ''}</span>
          <span class="dsb-cat-sku">${esc(p.sku || '')}</span><span class="dsb-cat-name" title="${esc(p.name)}">${esc(name || p.name)}</span>
          <span class="dsb-cat-specs">${specs.map(([k, v]) => `<span><i>${k ? esc(ttl(k)) : '&nbsp;'}</i><b>${esc(v)}</b></span>`).join('')}</span>
          <span class="dsb-cat-foot"><span class="dsb-cat-price">${money(p.price)}</span><span class="dsb-cat-select">${esc(t('Select'))}</span></span>`;
        const im = b.querySelector('.dsb-cat-img img'); if (im) im.addEventListener('error', () => im.remove());
        b.onclick = () => { if (side === 'front') state.frontYoke.product = p; else state.rearYoke.product = p; state.quote = null; requestQuote(); nextStep(); };
        grid.appendChild(b);
      });
      pag.innerHTML = '';
      if (data.pages > 1) {
        pag.innerHTML = `<button type="button" class="dsb-btn dsb-btn--ghost" data-pg="-1" ${data.page <= 1 ? 'disabled' : ''}>← ${esc(t('Prev'))}</button>
          <span>${esc(t('Page :p / :n (:t products)', {p: data.page, n: data.pages, t: data.total}))}</span>
          <button type="button" class="dsb-btn dsb-btn--ghost" data-pg="1" ${data.page >= data.pages ? 'disabled' : ''}>${esc(t('Next'))} →</button>`;
        $$('[data-pg]', pag).forEach((btn) => btn.onclick = () => { cat.page[side] += +btn.dataset.pg; loadCatalog(side); });
      }
    }).catch(() => { grid.innerHTML = `<div class="dsb-empty">${esc(t('Network error. Please try again.'))}</div>`; });
  }

  // ---------- technical drawings (the driveshaft designer's own line drawings) ----------
  const SECTION = {
    front: {weld: '01', flange: '02', trans: '09', yoke_shaft: '10', slip_flange: '11', slip_no_flange: '12'},
    rear: {weld: '01R', flange: '02R', slip_flange: '11R', slip_no_flange: '12R', carrier_end: '50', carrier_slip: '59'},
  };
  // the builder prices the front shaft only: its A1 runs to the bearing centre, which is the designer's B1.
  const CARRIER = {carrier_end: {cutAt: 'C1', pad: 12}, carrier_slip: {cutAt: 'C1', pad: 12}};
  const DIMMAP = {weld__weld: {a1: 'C1', c1: 'W1'}, slip_no_flange__weld: {a1: 'C1'}};
  const DIMDESC = {
    A1: t('Overall length, from the flange face or the end of the slip yoke to the far end'),
    C1: t('U-joint center to U-joint center'), W1: t('Weld to weld (the length of the tube)'), B1: t('Flange face or yoke end to the center of the support bearing'),
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
  function sectionArt(code, alt) {
    const box = document.createElement('span'); box.className = 'dsb-art'; box.setAttribute('role', 'img'); box.setAttribute('aria-label', alt);
    fetchSvg('/img/designer/sections/' + code + '.svg').then((txt) => {
      const s = inlineSvg(box, txt, '.dsb-art');
      s.setAttribute('viewBox', (s.getAttribute('viewBox') || '').startsWith('0 0 150') ? '20 26 110 48' : '0 26 75 48');
    }).catch(() => {});
    return box;
  }
  function renderPlan(box, plan, editable) {
    return fetchSvg('/driveshaft-designer/svg/' + plan.sections + '?style=uds').then((txt) => {
      const svg = inlineSvg(box, txt, '.dsb-plan-svg');
      if (plan.cut) {
        const ref = svg.querySelector(`.dims text[name="${plan.cut.cutAt}"]`), line = ref && ref.parentNode.querySelector('.dim-line');
        const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
        if (line && vb.length === 4) {
          const x2 = Math.max(+line.getAttribute('x1'), +line.getAttribute('x2'));
          svg.setAttribute('viewBox', [vb[0], vb[1], x2 + plan.cut.pad - vb[0], vb[3]].join(' '));
          svg.querySelectorAll('.dims text.dim-text').forEach((tx) => { if (tx.getAttribute('name') === plan.map.a1) tx.setAttribute('x', String((vb[0] + x2) / 2)); });
        }
        svg.querySelectorAll('text.front').forEach((f) => { if (/REAR/i.test(f.textContent)) f.remove(); });
        const keepLine = svg.querySelector(`.dims text[name="${plan.map.a1}"]`), kl = keepLine && keepLine.parentNode.querySelector('.dim-line');
        if (kl) {
          const end = Math.max(+kl.getAttribute('x1'), +kl.getAttribute('x2'));
          keepLine.parentNode.querySelectorAll('line').forEach((ln) => { if (Math.min(+ln.getAttribute('x1'), +ln.getAttribute('x2')) >= end - 0.5 && ln !== kl && !ln.classList.contains('dim-ext')) ln.remove(); });
        }
      }
      const keep = {};
      Object.entries(plan.map).forEach(([field, dim]) => { if (field !== 'c1' || !$('#dsb-field-c1').hidden) keep[dim] = field; });
      svg.querySelectorAll('.dims > g').forEach((g) => {
        g.querySelectorAll('text.dim-text').forEach((x) => { if (!keep[x.getAttribute('name')] && g.querySelectorAll('text.dim-text').length > 1) x.remove(); });
        const tx = g.querySelector('text.dim-text'); const dim = tx && tx.getAttribute('name');
        if (!dim || !keep[dim]) { g.remove(); return; }
        const field = keep[dim];
        tx.dataset.field = field; tx.dataset.label = field.toUpperCase();
        if (editable) { tx.setAttribute('tabindex', '0'); tx.setAttribute('role', 'button'); tx.setAttribute('aria-label', t('Enter :dim in inches', {dim: field.toUpperCase()})); }
      });
      svg.querySelectorAll('text.front').forEach((f) => f.remove());
      paintPlan(box);
      if (plan.cut) requestAnimationFrame(() => dropJoint(svg, plan));
      return svg;
    });
  }
  // after the bearing the shaft ends at its yoke: the U-joint cross and the other shaft's yoke are removed (owner, 2026-10-25)
  function dropJoint(svg, plan) {
    const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
    const ctm = svg.getScreenCTM(); if (!ctm || vb.length !== 4) return;
    const cxUnits = vb[0] + vb[2] - plan.cut.pad;
    const toScreen = (x) => { const p = svg.createSVGPoint(); p.x = x; p.y = 0; return p.matrixTransform(ctm).x; };
    const cx = toScreen(cxUnits), unit = Math.abs(toScreen(cxUnits + 1) - cx);
    if (!unit) return;
    const shapes = Array.from(svg.querySelectorAll('.parts path,.parts line,.parts polyline,.parts polygon,.parts rect,.parts ellipse,.parts circle'));
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
      const v = state[tx.dataset.field];
      tx.textContent = v ? tx.dataset.label + ' = ' + fmtIn(v) : tx.dataset.label;   // a plain letter until it has a value (no {A1} placeholders)
      tx.classList.toggle('is-set', !!v);
    });
  }
  function openDimInput(tx) {
    const holder = $('#dsb-plan'), inp = $('#dsb-plan-input');
    const hb = holder.getBoundingClientRect(), r = tx.getBoundingClientRect();
    const f = tx.dataset.field;
    inp.dataset.field = f; inp.value = state[f] || ''; inp.placeholder = f.toUpperCase();
    inp.hidden = false;
    inp.style.left = Math.max(4, Math.min(hb.width - 132, r.left - hb.left + r.width / 2 - 64)) + 'px';
    inp.style.top = Math.max(4, r.top - hb.top + r.height / 2 - 20) + 'px';
    $$('text.dim-text', holder).forEach((x) => x.classList.toggle('is-editing', x === tx));
    inp.focus(); inp.select();
  }
  function commitDim(move) {
    const inp = $('#dsb-plan-input'); if (inp.hidden) return;
    const f = inp.dataset.field, v = inp.value.trim().replace(',', '.').replace(/["\s]|in$/gi, '');
    if (v === '' || (+v > 0 && +v < 400)) { const field = $('#dsb-inp-' + f); field.value = v; field.dispatchEvent(new Event('input', {bubbles: true})); }
    inp.hidden = true; $$('text.dim-text.is-editing').forEach((x) => x.classList.remove('is-editing'));
    if (move) {
      const all = $$('#dsb-plan-svg text.dim-text[data-field]');
      const i = all.findIndex((x) => x.dataset.field === f); const next = all[i + (move > 0 ? 1 : -1)];
      if (next) openDimInput(next); else if (move > 0) $('[data-next="measure"]').focus();
    }
  }
  function currentDiagram() { return (CFG.appDiagrams || {})[state.front + '__' + state.rear] || ''; }
  function setupPlan() {
    const plan = planFor(state.front, state.rear);
    $('#dsb-plan').hidden = !plan;
    const dia = $('.dsb-measure-diagram'); dia.hidden = !!plan || !currentDiagram(); $('#dsb-diagram-img').src = currentDiagram();
    ['a1', 'c1'].forEach((f) => { const dim = plan && plan.map[f]; $(`[data-hint="${f}"]`).textContent = dim ? DIMDESC[dim] : (f === 'a1' ? DIMDESC.A1 : DIMDESC.C1); });
    if (!plan) return;
    $('#dsb-plan-code').textContent = tl((CFG.frontApps[state.front] || {}).label) + '  →  ' + tl((CFG.rearApps[state.rear] || {}).label);
    renderPlan($('#dsb-plan-svg'), plan, true).catch(() => { $('#dsb-plan').hidden = true; dia.hidden = !currentDiagram(); });
  }

  // ---------- measurements ----------
  // C1 is not asked when it equals A1 (u-joint at both ends, slip joint inside the shaft) or on a carrier section
  const c1Skip = () => (state.rear || '').startsWith('carrier') || (state.front === 'slip_no_flange' && state.rear === 'weld');
  // '' when the measurements are complete, else what is missing or wrong (owner, 2026-11-10: C1 is required)
  function measureError() {
    const a1 = parseFloat(state.a1) || 0, c1 = parseFloat(state.c1) || 0;
    if (!(a1 > 0)) return t('Please enter the length (A1).');
    if (c1Skip()) return '';
    if (!(c1 > 0)) return t('Please enter C1 (u-joint center to u-joint center).');
    if (c1 > a1) return t('C1 cannot be longer than A1. Please check both measurements.');
    return '';
  }
  function setupMeasure() {
    const c1hidden = c1Skip();
    $('#dsb-field-c1').hidden = c1hidden; if (c1hidden) { state.c1 = ''; $('#dsb-inp-c1').value = ''; }
    ['a1', 'c1'].forEach((f) => { const el = $('#dsb-inp-' + f); if (decimal(el.value) !== (state[f] || '')) el.value = state[f]; });
    $('#dsb-slip-note').hidden = true;
    const help = (ENG.measure_help || {})[CFG.pieces === 'two' ? 'two' : state.front] || '';
    const hb = $('#dsb-measure-help'); hb.hidden = !help; hb.textContent = tl(help);
    const an = $('#dsb-angle-note'); an.hidden = !ENG.angle_note; an.textContent = tl(ENG.angle_note || '');
    checkLength(); setupPlan();
  }
  // longest shaft for a material in the chosen series (settings), and the long-shaft band some reference products sell
  // (e.g. 5 in aluminum 1550: "87.75 in or less" / "88 to 93.50 in" at a higher price, measured seal to flange face)
  const lenFor = (mat) => { const m = (CFG.maxLengths || {})[state.seriesKey]; return m ? (parseFloat(m[mat]) || null) : null; };
  function longBand() {
    const bd = refProduct(); if (!bd || !bd.product) return null;
    const o = (bd.product.options || []).find((x) => /\b88\b|or over/i.test(x.name)); if (!o) return null;
    const nums = o.values.join(' ').match(/\d+(?:\.\d+)?/g); if (!nums) return null;
    const to = Math.max.apply(null, nums.map(parseFloat)); return to > 88 ? {from: 88, to} : null;
  }
  function checkLength() {
    const box = $('#dsb-length-check'); const a1 = parseFloat(state.a1) || 0;
    const alu = state.materialKey === 'aluminum', mat = alu ? t('aluminum') : t('steel');
    const bd = refProduct(), band = longBand();
    // the product's own limit when it is table-priced, otherwise the series limit for the material
    let max = (bd && bd.product && parseFloat(bd.product.max_len)) || lenFor(alu ? 'aluminum' : 'steel');
    if (band) max = Math.max(max || 0, band.to);
    if (!a1) { box.hidden = true; return true; }
    const bad = (msg) => { box.hidden = false; box.className = 'dsb-length-check is-bad'; box.textContent = msg; return false; };
    if (max && a1 > max) {
      const aluMax = lenFor('aluminum');
      // steel too short, aluminum long enough: say so; otherwise the honest answer is a two-piece shaft
      if (!alu && aluEligible() && aluMax && a1 <= aluMax)
        return bad(t('Steel tube in the :series series goes to :max in. Aluminum reaches :alu in: switch the material to aluminum, or call us for a two-piece shaft.', {series: seriesNum(), max, alu: aluMax}));
      return bad(t(':len in is longer than one :material tube can run safely in the :series series (:max in max). Call us for a two-piece shaft with a center support bearing: (813) 524-6491.', {len: a1, material: mat, series: seriesNum(), max}));
    }
    const qe = state.quote && state.quote.breakdown && state.quote.breakdown.error;
    if (qe === 'over_max' && isReference()) return bad(t('No tube we list covers this length in this series and material. Try the other material or a larger series, or call us for a two-piece shaft.'));
    const lp = localPrice(), pn = priceNow(); const od = isReference() ? (pn && pn.server ? pn.od : '') : (lp && lp.od);
    box.hidden = false; box.className = 'dsb-length-check';
    const inBand = band && a1 >= band.from;
    box.textContent = [od ? t('Lands on a :od tube', {od}) : '',
      inBand ? t('Long-shaft range (:from to :to in): priced with the long-shaft option', {from: band.from, to: band.to}) : (max ? t('Max :max in for :material :series', {max, material: mat, series: tl(seriesLabel())}) : '')].filter(Boolean).join(' · ');
    return true;
  }

  // ---------- real photos per end combination (tree file → photos) ----------
  function photoFor() {
    if (!state.front || !state.rear) return null;
    const key = (CFG.pieces === 'two' ? 'two:' : '') + state.front + '__' + state.rear;
    let p = PHOTOS[key] || null; let note = p ? p.note : '';
    if (p && state.materialKey === 'aluminum' && p.aluminum) { note = p.aluminum.note; p = Object.assign({}, p, p.aluminum); }
    if (!p) { const f = PHOTOS['front:' + state.front]; if (f) { p = f; note = f.note; } }
    return p ? {file: p.file, flip: !!p.flip, note, part: p.part} : null;
  }
  // ---------- critical speed (simply supported tube; the same first approximation for steel and aluminum) ----------
  function speedCalc() {
    const lp = localPrice(), pn = priceNow(); const a1 = parseFloat(state.a1) || 0, c1 = parseFloat(state.c1) || 0;
    // table-priced builds: the tube comes from the supplier product in the quote, never from our own size table
    const odSrc = isReference() ? (pn && pn.server ? pn.od : '') : (lp && lp.od);
    if (!odSrc || !a1) return null;
    const span = c1 > 0 && c1 < a1 ? c1 : a1;   // the formula wants u-joint centre to centre (C1); A1 alone errs on the safe side
    const od = parseFloat(String(odSrc).replace(/[^0-9.]/g, '')); const wall = (ENG.tube_walls || {})[state.materialKey || 'steel'] || 0.083;
    const k = ENG.critical_speed_k || 4705000, frac = ENG.safe_fraction || 0.75;
    const id = Math.max(0.1, od - 2 * wall); const nc = k * Math.sqrt(od * od + id * id) / (span * span);
    const out = {od, wall, id: +id.toFixed(3), length: span, joint: span === c1, critical: Math.round(nc), safe: Math.round(nc * frac), status: null};
    const mph = parseFloat(state.speed.mph) || 0, ratio = parseFloat(state.speed.ratio) || 0, tire = parseFloat(state.speed.tire) || 0;
    if (mph > 0 && ratio > 0 && tire > 0) { const rpm = mph * ratio * 336 / tire; out.rpm = Math.round(rpm); out.mph = mph; out.ratio = ratio; out.tire = tire; out.status = rpm <= nc * frac ? 'ok' : (rpm <= nc * 0.9 ? 'warn' : 'bad'); }
    // the next larger tube of this series and material at the same length, for the recommendation
    const rows = isReference() ? [] : odRows(); const bigger = rows.map((r) => parseFloat(String(r.od).replace(/[^0-9.]/g, ''))).filter((x) => x > od).sort((a, b) => a - b)[0];
    if (bigger) { const bid = Math.max(0.1, bigger - 2 * wall); out.next = {od: bigger, safe: Math.round(k * Math.sqrt(bigger * bigger + bid * bid) / (span * span) * frac)}; }
    return out;
  }
  const fmtOd = (v) => String(+(+v).toFixed(3));
  function speedText(sp) {
    const mat = state.materialKey === 'aluminum' ? t('aluminum') : t('steel');
    const tube = t(':od × :wall :material tube at :len in', {od: fmtOd(sp.od), wall: sp.wall, material: mat, len: fmtOd(sp.length)}) + ' ' + (sp.joint ? t('(joint to joint)') : t('(overall length; enter C1 for a closer figure)'));
    if (!sp.rpm) return t('This :tube is safe to :safe RPM (critical speed :crit RPM).', {tube, safe: sp.safe.toLocaleString('en-US'), crit: sp.critical.toLocaleString('en-US')});
    const lead = t('At :mph mph your shaft turns :rpm RPM; this :tube is safe to :safe RPM.', {mph: sp.mph, rpm: sp.rpm.toLocaleString('en-US'), tube, safe: sp.safe.toLocaleString('en-US')});
    if (sp.status === 'ok') return lead;
    const fix = sp.next ? t('A :od in tube would be safe to :safe RPM at this length.', {od: fmtOd(sp.next.od), safe: sp.next.safe.toLocaleString('en-US')}) : t('At this length a two-piece shaft with a center support bearing is the usual answer.');
    return lead + ' ' + (sp.status === 'warn' ? t('That is close to the limit.') : t('That is over the safe speed.')) + ' ' + fix + (sp.next && sp.status === 'bad' ? ' ' + t('Or consider a two-piece shaft.') : '');
  }
  function speedBadge(sp) {
    if (!sp) return '';
    const label = !sp.rpm ? t('safe to :safe RPM', {safe: sp.safe.toLocaleString('en-US')}) : ({ok: t('OK at :mph mph', {mph: sp.mph}), warn: t('near the limit at :mph mph', {mph: sp.mph}), bad: t('over the safe speed at :mph mph', {mph: sp.mph})})[sp.status];
    return `<span class="dsb-badge dsb-badge--${sp.status || 'info'}">${esc(t('Critical speed'))}: ${esc(label)}</span>`;
  }
  function renderSpeed() {
    // never rewrite a field that already says the same number: mid-typing "3," would lose its comma and become 373
    ['mph', 'ratio', 'tire'].forEach((k) => { const el = $('#dsb-speed-' + k); if (decimal(el.value) !== (state.speed[k] || '')) el.value = state.speed[k] || ''; });
    const sp = speedCalc(); const box = $('#dsb-speed-result');
    if (!sp) { box.hidden = true; return; }
    box.hidden = false; box.className = 'dsb-speed-result' + (sp.status ? ' is-' + sp.status : '');
    box.innerHTML = `${speedBadge(sp)}<p>${esc(speedText(sp))}</p><small>${esc(t('Inputs: Nc = 4.705×10⁶ × √(OD² + ID²) ÷ L², simply supported tube; OD :od in, wall :wall in, ID :id in, L :len in; safe = 75% of Nc.', {od: fmtOd(sp.od), wall: sp.wall, id: sp.id, len: fmtOd(sp.length)}))}${sp.rpm ? ' ' + esc(t('Shaft RPM = :mph × :ratio × 336 ÷ :tire.', {mph: sp.mph, ratio: sp.ratio, tire: sp.tire})) : ''}</small>`;
  }

  // ---------- build sheet ----------
  function endImage(side, key) { const a = (side === 'front' ? CFG.frontApps : CFG.rearApps)[key]; return a ? (a.image_clean || a.image || '') : ''; }
  function renderSheet() {
    const box = $('#dsb-sheet-draw');
    const want = state.front && state.rear ? 'plan:' + state.front + '__' + state.rear : state.front ? 'front:' + state.front : 'none';
    const ph = photoFor(); const pbox = $('#dsb-sheet-photo');
    const pkey = ph ? ph.file + (ph.flip ? '|f' : '') : '';
    if (pbox.dataset.shows !== pkey) { pbox.dataset.shows = pkey; pbox.hidden = !ph; pbox.innerHTML = ph ? `<img src="${esc(ph.file)}" alt="" class="${ph.flip ? 'is-flip' : ''}">` : ''; }
    const badge = $('[data-sheet-badge]'); badge.innerHTML = speedBadge(speedCalc());
    if (box.dataset.shows !== want) {
      box.dataset.shows = want;
      const plan = planFor(state.front, state.rear);
      if (want.startsWith('plan:') && plan) { box.innerHTML = '<div class="dsb-plan-svg"></div>'; renderPlan(box.firstChild, plan, false).catch(() => { box.innerHTML = currentDiagram() ? `<img src="${esc(currentDiagram())}" alt="">` : ''; }); }
      else if (want.startsWith('plan:') && currentDiagram()) box.innerHTML = `<img src="${esc(currentDiagram())}" alt="${esc(t('Configuration diagram'))}">`;
      else if (want.startsWith('front:') && SECTION.front[state.front]) { box.innerHTML = ''; box.appendChild(sectionArt(SECTION.front[state.front], tl(CFG.frontApps[state.front].label))); const more = document.createElement('span'); more.className = 'dsb-sheet-hint'; more.textContent = t('Now pick the rear end'); box.appendChild(more); }
      else box.innerHTML = `<span class="dsb-sheet-empty">${esc(t('Your driveshaft is drawn here as you choose the ends.'))}</span>`;
    } else { const p = box.querySelector('.dsb-plan-svg'); if (p) paintPlan(p); }
    const rows = [];
    state.answers.forEach((a, i) => rows.push([tl(a.q), tl(a.a), 'answer:' + i]));
    const yokeText = (y, app) => !y.kind || y.kind === 'none' ? '' : y.kind === 'own' ? t('Reuse mine') : y.kind === 'used' ? t('Good used yoke from our shop') : (y.product ? y.product.sku || y.product.name : t('New yoke'));
    const hwText = !state.hardware.kind ? '' : state.hardware.kind === 'own' ? t('No, I have mine') : (state.hardware.product ? state.hardware.product.sku : (state.hardware.kind === 'strap' ? t('Strap kit') : t('U-bolt kit')));
    const f = fam();
    if (f) {
      rows.push([t('Series'), state.seriesKey ? tl(seriesLabel()) + (state.variant ? ' (' + state.variant + ')' : '') : '', 'series']);
      if (aluEligible()) rows.push([t('Material'), state.materialKey ? (state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel')) : '', 'material']);
      if (!f.front_app) rows.push([t('Front'), state.front ? tl(CFG.frontApps[state.front].label) : '', 'front']);
      rows.push([t('Rear'), state.rear ? tl(CFG.rearApps[state.rear].label) : '', 'rear']);
      if (yokeTypeFor(state.rear)) rows.push([t('Rear yoke'), yokeText(state.rearYoke, state.rear), 'rear_yoke']);
      if (state.rear === 'weld') rows.push([t('Hardware'), hwText, 'hardware']);
      rows.push(['A1', fmtIn(state.a1), 'measure']);
      if (state.c1) rows.push(['C1', fmtIn(state.c1), 'measure']);
      if (frontYokeAsk()) rows.push([t('Front yoke'), yokeText(state.frontYoke, state.front), 'front_yoke']);
      if (state.tubeOd) rows.push([t('Tube OD'), state.tubeOd, '']);
      if (state.speed.mph && state.a1) rows.push([t('Speed check'), t(':mph mph, :ratio ratio, :tire in tire', {mph: state.speed.mph, ratio: state.speed.ratio, tire: state.speed.tire}), 'speed']);
    }
    $('#dsb-sheet-specs').innerHTML = rows.filter(([, v]) => v).map(([k, v, go]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd>${go ? `<button type="button" class="dsb-edit" data-edit="${esc(go)}" aria-label="${esc(t('Change'))} ${esc(k)}">${esc(t('Edit'))}</button>` : ''}</div>`).join('')
      || `<div class="is-empty"><dt>${esc(t('Nothing chosen yet'))}</dt><dd></dd></div>`;
    $$('[data-edit]').forEach((b) => b.onclick = () => edit(b.dataset.edit));
  }
  function edit(target) {
    if (target.startsWith('answer:')) {
      const i = +target.slice(7); const a = state.answers[i]; if (!a) return;
      state.answers = state.answers.slice(0, i); state.node = a.node; renderTree(); showStep('tree'); return;
    }
    if (fixedSteps().includes(target)) showStep(target);
  }

  // ---------- review (the configured shaft as a product) ----------
  function isDone() { return !!(state.family && state.seriesKey && state.front && state.rear && !measureError() && checkLength()
    && (!yokeTypeFor(state.rear) || (state.rearYoke.kind && (state.rearYoke.kind !== 'new' || state.rearYoke.product)))
    && (isReference() ? !!(refProduct() && refProduct().product) : ((!frontYokeAsk() || (state.frontYoke.kind && (state.frontYoke.kind !== 'new' || state.frontYoke.product)))
    && (state.rear !== 'weld' || (state.hardware.kind && (state.hardware.kind === 'own' || state.hardware.product)))))); }
  function markReady() {
    const done = isDone();
    const st = $('.dsb-cart-status'); st.classList.toggle('ready', done);
    st.querySelector('span').textContent = done ? t('Ready to add to your cart.') : t('Complete the steps to enable checkout.');
    $$('[data-add-btn]').forEach((b) => { b.disabled = !done; });
  }
  function titleText() {
    const s = tl(seriesLabel()).replace(/\s*series\s*$/i, '');
    const mat = state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel');
    const front = tl((CFG.frontApps[state.front] || {}).label || '').toLowerCase(), rear = tl((CFG.rearApps[state.rear] || {}).label || '').toLowerCase();
    return parseFloat(state.a1) > 0 ? t('Custom :series series :material driveshaft, :front to :rear, :len in', {series: s, material: mat.toLowerCase(), front, rear, len: String(+state.a1)}) : t('Custom :series series :material driveshaft, :front to :rear', {series: s, material: mat.toLowerCase(), front, rear});
  }
  function renderReview() {
    const f = fam() || {}; const fx = facts();
    $('#dsb-review-title').textContent = state.seriesKey ? titleText() : t('Custom driveshaft');
    const fits = (f.models || []).length ? t('Fits') + ': ' + f.models.join(', ') : '';
    $('#dsb-review-sub').textContent = [f.label ? tl(f.label) : '', fits, f.note ? tl(f.note) : ''].filter(Boolean).join(' · ');
    // the drawing only, no photo above it (owner, 2026-11-11)
    const rPlan = planFor(state.front, state.rear), rBox = $('#dsb-review-plan'), dImg = $('#dsb-review-diagram-img');
    rBox.hidden = !rPlan; dImg.hidden = !!rPlan; dImg.src = rPlan ? '' : currentDiagram();
    if (rPlan) renderPlan(rBox, rPlan, false).catch(() => { rBox.hidden = true; dImg.hidden = false; dImg.src = currentDiagram(); });
    // breakdown from the server quote
    const p = priceNow(); const q = state.quote; const bd = $('#dsb-breakdown'); const lines = [];
    const tier = state.materialKey === 'aluminum' ? 'new' : state.tier;
    const bdr = refProduct();
    if (bdr) {
      lines.push([t('Driveshaft') + ' — ' + [tl(seriesLabel()), state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel'), t('Tube :od', {od: bdr.tube_od}), t('new components')].join(', ') + (bdr.options ? Object.entries(bdr.options).filter(([k, v]) => shownOption(k, v)).map(([, v]) => ' · ' + ((CFG.optionLabels || {})[v] || v)).join('') : ''), money(bdr.am_price ? q.base : 0)]);
    } else if (p) lines.push([t('Base shaft') + ' — ' + [tl(seriesLabel()), state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel'), p.od ? t('Tube :od', {od: p.od}) : '', tier === 'new' ? t('Brand new') : t('Certified')].filter(Boolean).join(', '), money(q && q.base ? q.base : p.base)]);
    const yokeLine = (label, y, app) => { if (!y.kind || y.kind === 'none') return; lines.push([label + ' — ' + (y.kind === 'own' ? t('Reuse mine') : y.kind === 'used' ? t('Good used yoke from our shop') : (y.product ? y.product.sku + ' ' + y.product.name : t('New yoke'))), money(addonPrice(y, app))]); };
    if (!bdr && (frontYokeAsk() || state.frontYoke.kind === 'own')) yokeLine(t('Front yoke'), state.frontYoke, state.front);
    if (yokeTypeFor(state.rear)) yokeLine(t('Rear yoke'), state.rearYoke, state.rear);
    if (!bdr && state.rear === 'weld' && state.hardware.kind) lines.push([t('Hardware') + ' — ' + (state.hardware.kind === 'own' ? t('No, I have mine') : (state.hardware.product ? state.hardware.product.sku + ' ' + state.hardware.product.name : '')), money(state.hardware.product ? state.hardware.product.price : 0)]);
    bd.innerHTML = lines.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('') + (p && parseFloat(state.a1) > 0 ? `<div class="is-total"><dt>${esc(t('Total'))}</dt><dd>${money(p.final)}</dd></div>` : '');
    $('#dsb-tier').hidden = state.materialKey === 'aluminum' || !!bdr;
    $$('.dsb-tier-opt').forEach((c) => c.classList.toggle('dsb-tier-opt--on', c.dataset.selectTier === tier));
    // specs: every answer
    const specs = [];
    state.answers.forEach((a, i) => specs.push([tl(a.q), tl(a.a), 'answer:' + i]));
    if (fx.spline && !state.answers.some((a) => /spline/i.test(a.a))) specs.push([t('Spline count'), fx.spline, '']);
    specs.push([t('Series'), tl(seriesLabel()) + (state.variant ? ' (' + state.variant + ')' : ''), 'series']);
    specs.push([t('Material'), state.materialKey === 'aluminum' ? t('Aluminum') : t('Steel'), aluEligible() ? 'material' : '']);
    if (p && p.od) specs.push([t('Tube OD'), p.od, '']);
    specs.push([t('Front end'), tl((CFG.frontApps[state.front] || {}).label), f.front_app ? '' : 'front']);
    if (!bdr && (frontYokeAsk() || state.frontYoke.kind === 'own')) specs.push([t('Front yoke'), state.frontYoke.kind === 'own' ? t('Reuse mine') : state.frontYoke.kind === 'used' ? t('Good used yoke from our shop') : (state.frontYoke.product ? state.frontYoke.product.sku + ' · ' + state.frontYoke.product.name : ''), frontYokeAsk() ? 'front_yoke' : '']);
    if (state.frontYoke.model) specs.push([t('Transmission or transfer case model (optional)').replace(/ \(.*\)$/, ''), state.frontYoke.model, 'front_yoke']);
    specs.push([t('Rear end'), tl((CFG.rearApps[state.rear] || {}).label), 'rear']);
    if (yokeTypeFor(state.rear)) specs.push([t('Rear yoke'), state.rearYoke.kind === 'own' ? t('Reuse mine') : state.rearYoke.kind === 'used' ? t('Good used yoke from our shop') : (state.rearYoke.product ? state.rearYoke.product.sku + ' · ' + state.rearYoke.product.name : ''), 'rear_yoke']);
    if (bdr && bdr.options) Object.entries(bdr.options).filter(([k, v]) => shownOption(k, v)).forEach(([k, v]) => specs.push([optionName(k), tl((CFG.optionLabels || {})[v] || v), 'options']));
    if (!bdr && state.rear === 'weld') specs.push([t('Hardware'), state.hardware.kind === 'own' ? t('No, I have mine') : (state.hardware.product ? state.hardware.product.sku + ' · ' + state.hardware.product.name : ''), 'hardware']);
    specs.push([t('Length (A1), inches'), fmtIn(state.a1), 'measure']);
    if (state.c1) specs.push(['C1', fmtIn(state.c1), 'measure']);
    $('#dsb-review-specs').innerHTML = specs.filter(([, v]) => v).map(([k, v, go]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd>${go ? `<button type="button" class="dsb-edit" data-edit="${esc(go)}">${esc(t('Edit'))}</button>` : ''}</div>`).join('');
    $$('[data-edit]', $('#dsb-review-specs')).forEach((b) => b.onclick = () => edit(b.dataset.edit));
    const own = $('#dsb-own-note'); const anyOwn = state.frontYoke.kind === 'own' || state.rearYoke.kind === 'own';
    own.hidden = !anyOwn; own.textContent = anyOwn ? t('Reusing your yoke: ship it to us with your order number, or bring it to the shop. We fit it and balance the shaft with it. Questions: :phone', {phone: CFG.phone}) : '';
    const sp = speedCalc(); const sbox = $('#dsb-review-speed');
    sbox.hidden = !sp; if (sp) { sbox.className = 'dsb-speed-result' + (sp.status ? ' is-' + sp.status : ''); sbox.innerHTML = `${speedBadge(sp)}<p>${esc(speedText(sp))}</p>${!sp.rpm ? `<button type="button" class="dsb-edit" data-edit="speed">${esc(t('Check it for your vehicle'))}</button>` : `<button type="button" class="dsb-edit" data-edit="speed">${esc(t('Edit'))}</button>`}`; $$('[data-edit]', sbox).forEach((b) => b.onclick = () => edit(b.dataset.edit)); }
    const an = $('#dsb-review-angle'); an.hidden = !ENG.angle_note; an.textContent = tl(ENG.angle_note || '');
    $('#dsb-notes').value = state.notes;
    $('[data-share-url]').value = location.href;
    markReady();
  }
  function selectTier(tier) { state.tier = tier; state.quote = null; requestQuote(); renderPrice(); renderReview(); syncUrl(false); }

  // ---------- URL state ----------
  function serialize() {
    const p = new URLSearchParams();
    if (state.answers.length) p.set('dsb_p', state.answers.map((a) => a.idx).join('.'));
    const put = (k, v) => { if (v !== null && v !== undefined && v !== '' && v !== 0 && v !== 'none') p.set(k, v); };
    put('dsb_fam', state.family); put('dsb_series', state.seriesKey); put('dsb_var', state.variant); put('dsb_material', state.materialKey);
    put('dsb_front', state.front); put('dsb_rear', state.rear);
    put('dsb_fy', state.frontYoke.kind); put('dsb_fyid', state.frontYoke.product && state.frontYoke.product.id); put('dsb_fmod', state.frontYoke.model);
    put('dsb_ry', state.rearYoke.kind); put('dsb_ryid', state.rearYoke.product && state.rearYoke.product.id);
    put('dsb_fmk', state.frontYoke.make); put('dsb_rmk', state.rearYoke.make);
    put('dsb_hw', state.hardware.kind); put('dsb_hwid', state.hardware.product && state.hardware.product.id);
    put('dsb_a1', state.a1); put('dsb_c1', state.c1); if (state.tier === 'new') p.set('dsb_tier', 'new');
    put('dsb_mph', state.speed.mph); put('dsb_ratio', state.speed.ratio); put('dsb_tire', state.speed.tire);
    Object.entries(state.amOptions || {}).forEach(([k, v], i) => { if (v) p.set('dsb_o' + i, k + '=' + v); });
    // the optional steps already passed (speed check, supplier options), so a shared link or the assistant's link opens where it was left
    put('dsb_seen', (state.speedSeen ? 's' : '') + (state.optionsSeen ? 'o' : ''));
    put('dsb_step', state.step === 'tree' ? '' : state.step);
    return p;
  }
  let lastUrl = null;
  function syncUrl(push) {
    const qs = serialize().toString(); const url = location.pathname + (qs ? '?' + qs : '');
    const cur = location.pathname + location.search;
    if (url === lastUrl || url === cur) { lastUrl = url; try { history.replaceState({dsb: 1}, '', url); } catch (e) { /* ignore */ } return; }
    lastUrl = url;
    // the first entry is replaced (no empty history step), every later step is pushed so the browser's Back works
    try { history[push && history.state ? 'pushState' : 'replaceState']({dsb: 1}, '', url); } catch (e) { /* ignore */ }
  }
  function restore(p) {
    Object.assign(state, {step: 'tree', node: 'start', answers: [], family: null, dutyKey: null, subcatKey: null, seriesKey: null, variant: '', materialKey: null, front: null, rear: null, a1: '', c1: '', tier: 'certified', quote: null, tubeOd: '', speed: {mph: '', ratio: '', tire: ''}, speedSeen: false, optionsSeen: false});
    state.frontYoke = {kind: '', product: null, model: '', make: ''}; state.rearYoke = {kind: '', product: null, make: ''}; state.hardware = {kind: '', product: null};
    const path = (p.get('dsb_p') || '').split('.').filter((x) => x !== '').map(Number);
    for (const idx of path) {
      const node = treeNode(state.node); const o = node && node.options[idx]; if (!o) break;
      state.answers.push({node: state.node, idx, q: node.short || node.q, a: o.label, set: o.set || null});
      if (o.next) state.node = o.next; else { applyFamily(o.leaf); state.node = null; break; }
    }
    // a saved design (account) or an older share link carries the keys without a path
    if (!state.family && p.get('dsb_series')) {
      const duty = p.get('dsb_duty') || 'light_medium';
      let famId = 'LEGACY';
      if (duty === 'heavy') Object.entries(CFG.tree.heavy.subcategories || {}).forEach(([k, sc]) => { if (sc.series && sc.series[p.get('dsb_series')]) famId = 'HD:' + k; });
      if (p.get('dsb_fam') && (FAMS[p.get('dsb_fam')] || p.get('dsb_fam').startsWith('HD:'))) famId = p.get('dsb_fam');
      applyFamily(famId); state.node = null;
    }
    if (!state.family) return;
    const f = fam();
    if (p.get('dsb_series') && seriesOptions().some((o) => o.key === p.get('dsb_series'))) { state.seriesKey = p.get('dsb_series'); state.variant = VARIANTS[state.seriesKey] === p.get('dsb_var') ? p.get('dsb_var') : ''; }
    state.materialKey = state.seriesKey ? (aluEligible() && p.get('dsb_material') === 'aluminum' ? 'aluminum' : 'steel') : null;
    if (!f.front_app && p.get('dsb_front') && CFG.frontApps[p.get('dsb_front')]) state.front = p.get('dsb_front');
    if (p.get('dsb_rear') && CFG.rearApps[p.get('dsb_rear')] && rearApps().some(([k]) => k === p.get('dsb_rear'))) state.rear = p.get('dsb_rear');
    const kinds = ['new', 'used', 'own'], make = (k) => (/^[a-z0-9-]{1,20}$/.test(p.get(k) || '') ? p.get(k) : '');
    if (kinds.includes(p.get('dsb_fy')) && yokeTypeFor(state.front) && f.yoke_choice !== false) state.frontYoke = {kind: p.get('dsb_fy'), product: products[p.get('dsb_fyid')] || null, model: p.get('dsb_fmod') || '', make: make('dsb_fmk')};
    if (kinds.includes(p.get('dsb_ry')) && yokeTypeFor(state.rear)) state.rearYoke = {kind: p.get('dsb_ry'), product: products[p.get('dsb_ryid')] || null, make: make('dsb_rmk')};
    if (['strap', 'ubolt', 'own'].includes(p.get('dsb_hw')) && state.rear === 'weld') state.hardware = {kind: p.get('dsb_hw'), product: products[p.get('dsb_hwid')] || null};
    if (parseFloat(p.get('dsb_a1')) > 0) state.a1 = p.get('dsb_a1');
    if (parseFloat(p.get('dsb_c1')) > 0) state.c1 = p.get('dsb_c1');
    if (p.get('dsb_tier') === 'new' || state.materialKey === 'aluminum') state.tier = 'new';
    state.amOptions = {}; for (let i = 0; i < 6; i++) { const kv = p.get('dsb_o' + i); if (kv && kv.includes('=')) state.amOptions[kv.slice(0, kv.indexOf('='))] = kv.slice(kv.indexOf('=') + 1); }
    state.speed = {mph: parseFloat(p.get('dsb_mph')) > 0 ? p.get('dsb_mph') : '', ratio: parseFloat(p.get('dsb_ratio')) > 0 ? p.get('dsb_ratio') : '', tire: parseFloat(p.get('dsb_tire')) > 0 ? p.get('dsb_tire') : ''};
    const seen = p.get('dsb_seen') || ''; state.speedSeen = seen.includes('s'); state.optionsSeen = seen.includes('o');
    // products that are not cached yet are fetched by id (the kind stays; the pick step reopens if it fails)
    const fetches = [];
    const want = (y, app, id) => { if (y.kind === 'new' && !y.product && id) fetches.push(fetch(CFG.endpoints.yokes + '?' + new URLSearchParams({type: yokeTypeFor(app), id})).then((r) => r.json()).then(({data}) => { const pr = (data.products || [])[0]; if (pr) { remember(pr); y.product = pr; } }).catch(() => {})); };
    want(state.frontYoke, state.front, p.get('dsb_fyid')); want(state.rearYoke, state.rear, p.get('dsb_ryid'));
    if (['strap', 'ubolt'].includes(state.hardware.kind) && !state.hardware.product && p.get('dsb_hwid')) fetches.push(fetch(CFG.endpoints.yokes + '?' + new URLSearchParams({type: state.hardware.kind, series: state.seriesKey || ''})).then((r) => r.json()).then(({data}) => { const pr = (data.products || []).find((x) => String(x.id) === p.get('dsb_hwid')); if (pr) { remember(pr); state.hardware.product = pr; } }).catch(() => {}));
    return fetches.length ? Promise.all(fetches) : null;
  }
  function firstIncomplete() {
    const s = fixedSteps();
    for (const st of s) {
      if (st === 'series' && !state.seriesKey) return st;
      if (st === 'material' && !state.materialKey) return st;
      if (st === 'front' && !state.front) return st;
      if (st === 'rear' && !state.rear) return st;
      if (st === 'rear_yoke' && !state.rearYoke.kind) return st;
      // a yoke already picked (a saved design from before this step) needs no make
      if (st === 'rear_brand' && !state.rearYoke.make && !state.rearYoke.product) return st;
      if (st === 'rear_pick' && !state.rearYoke.product) return st;
      if (st === 'hardware' && (!state.hardware.kind || (state.hardware.kind !== 'own' && !state.hardware.product))) return st;
      if (st === 'measure' && measureError()) return st;
      if (st === 'speed' && !state.speedSeen && !state.speed.mph) return st;
      if (st === 'options' && !Object.keys(state.amOptions || {}).length && !state.optionsSeen) return st;
      if (st === 'front_yoke' && !state.frontYoke.kind) return st;
      if (st === 'front_brand' && !state.frontYoke.make && !state.frontYoke.product) return st;
      if (st === 'front_pick' && !state.frontYoke.product) return st;
    }
    return 'review';
  }
  function boot(fromPop) {
    const p = new URLSearchParams(location.search);
    const pending = restore(p);
    const land = () => {
      if (!state.family) { renderTree(); showStep('tree', {silent: fromPop}); return; }
      const want = p.get('dsb_step'); const s = fixedSteps();
      showStep(s.includes(want) && s.indexOf(want) <= s.indexOf(firstIncomplete()) ? want : firstIncomplete(), {silent: fromPop});
      requestQuote();
    };
    if (pending) pending.then(land); else land();
  }

  // ---------- submit / save ----------
  function payload() {
    const fx = facts();
    return {
      pieces: CFG.pieces, family: state.family || '', answers: JSON.stringify(state.answers.map((a) => ({q: a.q, a: a.a}))),
      duty_key: state.dutyKey || '', subcat_key: state.subcatKey || '', series_key: state.seriesKey || '', series_variant: state.variant || '',
      material: state.materialKey || 'steel', price_tier: state.materialKey === 'aluminum' ? 'new' : state.tier,
      front_app: state.front || '', rear_app: state.rear || '', length: '', a1: state.a1 || 0, c1: state.c1 || 0, b1: 0,
      front_yoke_kind: state.frontYoke.kind || 'none', front_yoke_product_id: state.frontYoke.product ? state.frontYoke.product.id : 0,
      front_spline: fx.spline || '', front_trans_model: state.frontYoke.model || (fx.model || ''),
      rear_yoke_kind: state.rearYoke.kind || 'none', rear_yoke_product_id: state.rearYoke.product ? state.rearYoke.product.id : 0,
      hardware_kind: state.hardware.kind || 'none', hardware_product_id: state.hardware.product ? state.hardware.product.id : 0,
      am_options: JSON.stringify(state.amOptions || {}), notes: state.notes || '', speed_mph: state.speed.mph || '', axle_ratio: state.speed.ratio || '', tire_in: state.speed.tire || '',
    };
  }
  let submitting = false;
  function submit() {
    if (submitting) return;
    if (!isDone()) { toast(t('Please complete the driveshaft configuration before adding to cart.')); return; }
    submitting = true;
    const btns = $$('[data-add-btn]'); btns.forEach((b) => { b.disabled = true; b.classList.add('is-busy'); });
    const body = new URLSearchParams(Object.assign(payload(), {quantity: $('#dsb-qty-review').value || 1}));
    fetch(CFG.endpoints.submit, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'}, body})
      .then((r) => r.json()).then((res) => {
        submitting = false; btns.forEach((b) => { b.classList.remove('is-busy'); });
        if (!res.success) { btns.forEach((b) => { b.disabled = false; }); toast((res.errors || [t('Error')]).join(' ')); return; }
        toast(t('Added to cart!'));
        const badge = document.querySelector('[data-cart-count]');
        if (badge) { badge.textContent = res.data.cart_count; badge.hidden = false; }
        $('#dsb-confirm-price').textContent = money(res.data.price.final);
        $('#dsb-confirm-summary').textContent = res.data.title || res.data.summary;
        $$('.dsb-step').forEach((s) => s.classList.remove('dsb-step--active'));
        $('#dsb-confirm').hidden = false; $('.dsb-cart-footer').hidden = true;
        wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
      }).catch(() => { submitting = false; btns.forEach((b) => { b.disabled = false; b.classList.remove('is-busy'); }); toast(t('Network error. Please try again.')); });
  }
  function saveDesign() {
    if (!CFG.loggedIn) { toast(t('Sign in to save your design.')); setTimeout(() => { location.href = CFG.loginUrl.replace(/next=.*$/, 'next=' + encodeURIComponent(location.pathname + location.search)); }, 900); return; }
    fetch(CFG.endpoints.save, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload())})
      .then((r) => r.json()).then((res) => { toast(res.success ? t('Design saved to your account.') + ' ' + res.ref : (res.message || (res.errors || []).join(' ') || t('Error'))); })
      .catch(() => toast(t('Network error. Please try again.')));
  }

  // back to the first question with nothing chosen; the browser's Back still returns to the build that was cleared
  function startOver() {
    if (!window.confirm(t('Start over? Your answers will be cleared.'))) return;
    restore(new URLSearchParams());
    state.amOptions = {}; state.notes = ''; cat.filters = {front: {}, rear: {}}; cat.page = {front: 1, rear: 1};
    $('#dsb-notes').value = ''; $$('.dsb-qty-input').forEach((x) => { x.value = 1; });
    renderTree(); showStep('tree'); requestQuote();
  }

  // ---------- bind ----------
  function bind() {
    $('[data-back]').onclick = goBack;
    $('[data-restart]').onclick = startOver;
    ['a1', 'c1'].forEach((f) => {
      $(`#dsb-inp-${f}`).addEventListener('input', (e) => { state[f] = decimal(e.target.value); checkLength(); paintPlan($('#dsb-plan-svg')); renderSheet(); renderPrice(); requestQuote(); syncUrl(false); });
    });
    $('[data-next="measure"]').onclick = () => { const me = measureError(); if (me) { toast(me); $(parseFloat(state.a1) > 0 ? '#dsb-inp-c1' : '#dsb-inp-a1').focus(); return; } if (!checkLength()) { toast($('#dsb-length-check').textContent); return; } nextStep(); };
    ['mph', 'ratio', 'tire'].forEach((k) => $('#dsb-speed-' + k).addEventListener('input', (e) => { state.speed[k] = decimal(e.target.value); renderSpeed(); renderSheet(); syncUrl(false); }));
    $('[data-next="speed"]').onclick = () => { state.speedSeen = true; requestQuote(); nextStep(); };
    $('[data-skip="speed"]').onclick = () => { state.speedSeen = true; state.speed = {mph: '', ratio: '', tire: ''}; renderSheet(); nextStep(); };
    $('#dsb-front-model').addEventListener('input', (e) => { state.frontYoke.model = e.target.value; syncUrl(false); });
    $$('.dsb-tier-opt').forEach((c) => c.onclick = () => selectTier(c.dataset.selectTier));
    $('#dsb-notes').addEventListener('input', (e) => { state.notes = e.target.value; });
    $('[data-action="copy"]').onclick = () => {
      const inp = $('[data-share-url]'); inp.value = location.href;
      (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).then(() => toast(t('Share link copied to clipboard.'))).catch(() => { inp.select(); document.execCommand('copy'); toast(t('Share link copied to clipboard.')); });
    };
    $$('[data-qty]').forEach((b) => b.onclick = () => { const inp = b.parentElement.querySelector('.dsb-qty-input'); inp.value = Math.max(1, (+inp.value || 1) + (b.dataset.qty === '+' ? 1 : -1)); $$('.dsb-qty-input').forEach((x) => { x.value = inp.value; }); });
    $$('.dsb-qty-input').forEach((inp) => inp.addEventListener('input', () => $$('.dsb-qty-input').forEach((x) => { x.value = inp.value; })));
    $$('[data-add-btn]').forEach((b) => b.onclick = submit);
    $('[data-save-btn]').onclick = saveDesign;
    const planBox = $('#dsb-plan-svg'), planInp = $('#dsb-plan-input');
    planBox.addEventListener('click', (e) => { const tx = e.target.closest('text.dim-text[data-field]'); if (tx) openDimInput(tx); });
    planBox.addEventListener('keydown', (e) => { const tx = e.target.closest('text.dim-text[data-field]'); if (tx && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openDimInput(tx); } });
    planInp.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); commitDim(1); }
      else if (e.key === 'Tab') { e.preventDefault(); commitDim(e.shiftKey ? -1 : 1); }
      else if (e.key === 'Escape') { planInp.hidden = true; $$('text.dim-text.is-editing').forEach((x) => x.classList.remove('is-editing')); }
    });
    planInp.addEventListener('blur', () => { const f = planInp.dataset.field; setTimeout(() => { if (!planInp.hidden && planInp.dataset.field === f && document.activeElement !== planInp) commitDim(0); }, 150); });
    $$('[data-mbar-go]').forEach((b) => b.onclick = () => $('#dsb-sheet').scrollIntoView({behavior: 'smooth', block: 'start'}));
    const mbar = document.querySelector('.dsb-mbar');
    if (mbar && 'IntersectionObserver' in window) new IntersectionObserver((en) => mbar.classList.toggle('is-off', en[0].isIntersecting), {threshold: 0.15}).observe($('#dsb-sheet'));
    window.addEventListener('popstate', () => { lastUrl = location.pathname + location.search; boot(true); });
  }

  bind(); boot(false);
})();
