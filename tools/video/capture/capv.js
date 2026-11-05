// Find-by-Vehicle walk + product page + full home page, 1440x900 @2x, no browser chrome. Any visible "udshafts" text is rewritten before the shot.
const { chromium } = require(process.env.PWC); const fs = require('fs');
const OUT = process.env.HOME + '/pti-work/reel/remotion/public/v3/shots/'; fs.mkdirSync(OUT, { recursive: true }); const ck = require('./cookie.json');
const HIDE = `.floaty,[data-aitr],.aitr,[href^="/admin"],.ucart{display:none!important} *{caret-color:transparent} html{scroll-behavior:auto!important}`;
const meta = {};
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  await ctx.addCookies([{ name: ck.name, value: ck.value, domain: 'dev.udshafts.com', path: '/', httpOnly: true, secure: true }, { name: 'uds_lang', value: 'en', domain: 'dev.udshafts.com', path: '/' }]);
  const p = await ctx.newPage();
  const fix = () => p.evaluate(() => {
    const rep = [[/federico schwindt/gi, 'Alex Morgan'], [/owner@example\.com/gi, 'alex@example.com'], [/federico|leonel/gi, 'Alex'], [/schwindt/gi, 'Morgan'], [/(?:dev\.)?udshafts\.com/gi, 'universaldriveshafts.com']];
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
    while ((n = w.nextNode())) { let t = n.nodeValue; rep.forEach(([a, c]) => { t = t.replace(a, c); }); if (t.trim() === 'FS') t = t.replace('FS', 'AM'); n.nodeValue = t; }
    document.querySelectorAll('input,textarea').forEach((i) => { rep.forEach(([a, c]) => { if (a.test(i.value)) i.value = i.value.replace(a, c); }); });
  });
  const settle = async () => { await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await p.addStyleTag({ content: HIDE }).catch(() => {}); await fix(); await p.mouse.move(1439, 899); await p.waitForTimeout(700); };
  const go = async (u) => { await p.goto('https://dev.udshafts.com' + u, { waitUntil: 'load', timeout: 60000 }); await settle(); };
  const bb = async (sel, i = 0) => { const l = p.locator(sel).nth(i); const r = await l.boundingBox().catch(() => null); return r ? { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) } : null; };
  const shot = async (name, extra = {}, full = false) => { await p.screenshot({ path: OUT + name + '.png', fullPage: full }); meta[name] = { scroll: await p.evaluate(() => scrollY), ...extra }; console.log('shot', name, JSON.stringify(extra).slice(0, 200)); };

  // ---- home, full page (the landing scroll)
  await go('/'); await shot('home-full', {}, true);

  // ---- find by vehicle
  await go('/shop/complete-driveshafts/by-vehicle');
  const sels = { make: '[data-v=make]', year: '[data-v=year]', model: '[data-v=model]' };
  const boxes = async () => ({ make: await bb(sels.make), year: await bb(sels.year), model: await bb(sels.model) });
  const lists = { makes: await p.$$eval(sels.make + ' option', (o) => o.map((x) => x.value).filter(Boolean)) };
  await shot('veh-0', { sel: await boxes() });
  await p.selectOption(sels.make, 'Ford'); await p.waitForFunction(() => !document.querySelector('[data-v=year]').disabled, null, { timeout: 15000 }); await settle();
  lists.years = await p.$$eval(sels.year + ' option', (o) => o.map((x) => x.value).filter(Boolean));
  await shot('veh-1', { sel: await boxes() });
  await p.selectOption(sels.year, '2015'); await p.waitForFunction(() => !document.querySelector('[data-v=model]').disabled, null, { timeout: 15000 }); await settle();
  lists.models = await p.$$eval(sels.model + ' option', (o) => o.map((x) => x.value).filter(Boolean));
  await shot('veh-2', { sel: await boxes(), lists });
  await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 60000 }).catch(() => {}), p.selectOption(sels.model, 'F150')]); await settle();
  if (!(await p.locator('table.ptbl').count())) { await p.locator('form[data-vfilter] button[type=submit], form[data-vfilter] [type=submit]').first().click().catch(() => {}); await p.waitForLoadState('load'); await settle(); }
  // bring the table to the top third of the viewport
  await p.evaluate(() => { const t = document.querySelector('.ptbl-wrap'); if (t) window.scrollTo(0, Math.max(0, t.getBoundingClientRect().top + scrollY - 190)); }); await p.waitForTimeout(500);
  const rows = []; const n = Math.min(8, await p.locator('table.ptbl tbody tr').count());
  for (let i = 0; i < n; i++) { const r = p.locator('table.ptbl tbody tr').nth(i); rows.push({ box: await bb('table.ptbl tbody tr', i), pn: (await r.locator('.p-pn').first().innerText().catch(() => '')).trim(), price: (await r.locator('.p-price').first().innerText().catch(() => '')).trim().replace(/\s+/g, ' '), href: await r.locator('.p-pn').first().getAttribute('href').catch(() => null) }); }
  await shot('veh-3', { sel: await boxes(), table: await bb('table.ptbl'), head: await bb('table.ptbl thead'), rows, count: (await p.locator('.shop-count').innerText().catch(() => '')).trim() });
  await p.locator('table.ptbl tbody tr').first().hover(); await p.waitForTimeout(300); await shot('veh-3h', { rows });
  // product page
  await Promise.all([p.waitForNavigation({ waitUntil: 'load', timeout: 60000 }), p.locator('table.ptbl tbody tr').first().locator('.p-pn').first().click()]); await settle();
  await shot('veh-4', { price: await bb('.fpp-price'), priceText: (await p.locator('.fpp-price').innerText().catch(() => '')).trim(), ready: await bb('text=/Ready to ship/i'), readyText: (await p.locator('text=/Ready to ship/i').first().innerText().catch(() => '')).trim(), stock: await bb('text=/in stock/i'), buy: await bb('.fpp-buy button, .fpp-buy a'), head: await bb('.fpp-head'), title: (await p.locator('.fpp-head h1').innerText().catch(() => '')).trim() });

  // ---- builder end (re-shot with the share URL rewritten)
  await go('/online-builder'); // just to warm; the real end state lives in the old shots. Only redo the final page if the wizard URL is restorable.
  fs.writeFileSync(OUT + 'meta.json', JSON.stringify(meta, null, 1)); await b.close(); console.log('done');
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
