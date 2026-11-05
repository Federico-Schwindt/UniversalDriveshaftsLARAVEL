const { chromium } = require(process.env.PWC); const fs = require('fs');
const style = process.argv[2] || '1';
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ storageState: process.env.SP + '/pti/state.json', viewport: { width: 1400, height: 1000 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); const net = [];
  p.on('response', r => { const u = r.url(); if (/driveshaft-builder|driveshaft-sections|driveshaft-designer/.test(u) && !/\.(png|css)$/.test(u)) net.push(r.status() + ' ' + u.slice(0, 140)); });
  await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/' + style, { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(1500);
  const out = { style, steps: [] };
  for (let step = 0; step < 8; step++) {
    const st = await p.evaluate(() => {
      const sc = document.querySelector('.section-chooser'), res = document.querySelector('.result');
      const vis = el => el && !el.classList.contains('hidden') && el.offsetParent !== null;
      if (vis(res)) return { done: true };
      const loc = (sc.querySelector('.next-choice') || {}).innerText || '';
      const cards = [...sc.querySelectorAll('.sections [class*="section"], .sections .card-box, .sections a, .sections > div > *')].filter(e => e.querySelector('img')).map(e => ({ text: e.innerText.replace(/\s+/g, ' ').trim().slice(0, 60), img: (e.querySelector('img') || {}).src || '', data: Object.assign({}, e.dataset) }));
      const preview = [...sc.querySelectorAll('.preview-sections img')].map(i => i.src.split('/').pop());
      return { done: false, loc: loc.replace(/\s+/g, ' ').trim(), count: cards.length, cards: cards.slice(0, 40), preview, sectionsHTML: sc.querySelector('.sections').innerHTML.slice(0, 900) };
    });
    await p.screenshot({ path: `${process.env.SP}/pti/s${style}-step${step}.png`, fullPage: true });
    out.steps.push(st); if (st.done) break;
    if (!st.count) { out.steps.push({ note: 'no cards found' }); break; }
    // click the first card (image) to advance
    await p.evaluate(() => { const e = [...document.querySelectorAll('.section-chooser .sections *')].find(x => x.querySelector && x.querySelector('img')); (e.querySelector('a') || e).click(); });
    await p.waitForTimeout(1800);
  }
  const result = await p.evaluate(() => { const r = document.querySelector('.result'); if (!r) return null; const svg = r.querySelector('svg'); return { formula: (r.querySelector('.formula') || {}).innerText, dataUrl: r.querySelector('.driveshaft-svg').dataset.url, svgSize: svg ? svg.outerHTML.length : 0, svgTexts: svg ? [...svg.querySelectorAll('text')].map(t => t.textContent.trim()).filter(Boolean).slice(0, 40) : [], clickable: svg ? [...svg.querySelectorAll('[class]')].map(e => e.getAttribute('class')).filter((v, i, a) => a.indexOf(v) === i).slice(0, 30) : [], userView: (r.querySelector('.user-view') || {}).innerText }; });
  out.result = result; if (result) fs.writeFileSync(`${process.env.SP}/pti/result-${style}.html`, await p.evaluate(() => document.querySelector('.result').outerHTML));
  // try the dimension input: click the first dimension text in the svg, type a value
  if (result && result.svgTexts.length) {
    const dim = await p.evaluate(() => { const t = [...document.querySelectorAll('.result svg text')].find(x => /^[A-Z]\d?$|^[A-Z]\d$/.test(x.textContent.trim())); if (!t) return null; t.dispatchEvent(new MouseEvent('click', { bubbles: true })); return t.textContent.trim(); });
    await p.waitForTimeout(600);
    const inp = await p.evaluate(() => { const i = document.querySelector('.dim-input'); return { hidden: i.classList.contains('hidden'), style: i.getAttribute('style'), value: i.value, placeholder: i.placeholder, defaultText: i.dataset.defaultText }; });
    out.dimClick = { label: dim, input: inp };
    if (!inp.hidden) { await p.keyboard.type('24.5'); await p.keyboard.press('Enter'); await p.waitForTimeout(500); out.afterType = await p.evaluate(() => [...document.querySelectorAll('.result svg text')].map(t => t.textContent.trim()).filter(Boolean).slice(0, 40)); }
    await p.screenshot({ path: `${process.env.SP}/pti/s${style}-result.png`, fullPage: true });
  }
  out.net = net.slice(0, 20);
  console.log(JSON.stringify(out, null, 1)); await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
