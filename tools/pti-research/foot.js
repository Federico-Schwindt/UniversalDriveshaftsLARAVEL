const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch();
 for (const [w,f] of [[1440,'foot-d'],[390,'foot-m']]) { const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' }); const el = p.locator('.unews'); await el.scrollIntoViewIfNeeded();
  await el.screenshot({ path: process.env.HOME + '/pti-work/' + f + '.png' }); }
 await b.close(); })();
