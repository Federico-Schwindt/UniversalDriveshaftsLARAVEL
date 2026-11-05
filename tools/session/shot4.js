const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch({ headless: true });
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  await p.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
  await p.screenshot({ path: process.env.SP + '/home-phone-top.png', clip: { x: 0, y: 0, width: 390, height: 1240 }, fullPage: true });
  const d = await b.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await d.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' }); await d.waitForTimeout(600);
  await d.screenshot({ path: process.env.SP + '/home-desk-top.png', clip: { x: 0, y: 0, width: 1440, height: 1080 }, fullPage: true });
  console.log('home shots ok'); await b.close(); })().catch(e => { console.error('FAILED', e.message.slice(0,200)); process.exit(1); });
