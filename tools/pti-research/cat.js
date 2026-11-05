const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ storageState: process.env.OUT + '/state.json', userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); let shown = 0;
  p.on('request', r => { if (/api\.powertrain/.test(r.url()) && /component_parts\/(transmission|parts|type)|\/parts?\//.test(r.url()) && shown++ < 3) { const h = r.headers(); console.log('REQ', r.method(), r.url(), '\n hdr:', Object.keys(h).map(k => k + (/(auth|cookie|token)/i.test(k) ? '=<' + h[k].length + ' chars>' : '')).join(',')); } });
  p.on('response', async r => { const u = r.url(); if (/api\.powertrain/.test(u) && /transmission-yokes|\/part/.test(u)) { const t = await r.text().catch(() => ''); fs.writeFileSync(process.env.OUT + '/resp-' + (u.includes('/part') ? 'part' : 'list') + '.json', t); console.log('RESP', r.status(), u, t.length); } });
  await p.goto('https://c2.powertrainindustries.com/catalogs/component-parts/type/transmission-yokes/all/all/all/all/all/all', { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(2000);
  const first = await p.evaluate(() => [...document.querySelectorAll('a[href^="/parts/"]')].slice(0, 3).map(a => a.getAttribute('href')));
  console.log('part links', first);
  if (first[0]) { await p.goto('https://c2.powertrainindustries.com' + first[0], { waitUntil: 'networkidle', timeout: 90000 }); await p.waitForTimeout(1500); fs.writeFileSync(process.env.OUT + '/part.txt', await p.evaluate(() => document.body.innerText)); }
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
