const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage();
  const red = s => (s || '').replace(/"(password|api_key|key|token|x-api-key|session)"\s*:\s*"[^"]*"/gi, '"$1":"<redacted>"').replace(/password=[^&]*/,'password=<r>');
  p.on('request', r => { if (/api\.powertrain/.test(r.url())) { const h = r.headers(); if (/login|avail|stock|inventory|warehouse/.test(r.url())) { const buf = r.postDataBuffer(); console.log('REQ', r.method(), r.url(), 'ctype:', h['content-type'], 'bodylen:', buf ? buf.length : 0, 'headers:', Object.entries(h).map(([k,v]) => k + '=' + (/auth|key|user|pass/i.test(k) ? '<' + v.length + (/^basic/i.test(v) ? ' BASIC' : '') + '>' : v.slice(0,40))).join(' ; ')); } } });
  p.on('response', async r => { if (/api\.powertrain.*(login|avail|stock|inventory|warehouse)/.test(r.url())) console.log('RESP', r.url(), red(await r.text().catch(()=>'')).slice(0, 700)); });
  await p.goto('https://c2.powertrainindustries.com/auth', { waitUntil: 'domcontentloaded', timeout: 60000 }); await p.waitForTimeout(1500);
  await p.fill('input[name="username"]', process.env.PTI_USER); await p.fill('input[name="password"]', process.env.PTI_PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}), p.click('button[type="submit"]')]);
  const key = await p.evaluate(() => Object.keys(localStorage).map(k => k + ':' + String(localStorage.getItem(k)).length));
  console.log('localStorage keys', key, 'cookies', (await ctx.cookies()).map(c => c.name + '@' + c.domain));
  p.on('request', r => { if (/api\.powertrain/.test(r.url())) console.log('  call', r.url().replace('https://api.powertrainindustries.com', '')); });
  await p.goto('https://c2.powertrainindustries.com/parts/4103-323', { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(2500);
  console.log((await p.evaluate(() => document.body.innerText)).split('RELATED')[0].slice(-500));
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
