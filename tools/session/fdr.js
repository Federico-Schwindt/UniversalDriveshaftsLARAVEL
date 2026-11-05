const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  await p.goto('https://dev.udshafts.com/catalog/driveshafts?make=Audi&year=2020&model=A4', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
  const el = await p.$('.fdr-card'); if (el) await el.screenshot({ path: process.env.SP + '/fdr.png' }); else console.log('no card');
  await b.close(); })();
