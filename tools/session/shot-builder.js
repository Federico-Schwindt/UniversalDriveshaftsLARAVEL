const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  // move into the real flow so the shot shows duty cards, not the mode chooser
  await p.click('.dsb-mode-card[data-mode="custom"]').catch(() => {});
  await p.waitForTimeout(500);
  const el = await p.$('#ubs-dsb');
  await el.screenshot({ path: process.env.SP + '/builder-el.png' });
  console.log('builder element screenshot taken');
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
