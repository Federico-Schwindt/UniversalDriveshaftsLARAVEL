const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  for (const [name, vp] of [['desk', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
    const p = await b.newPage({ viewport: vp, deviceScaleFactor: 1 });
    const errs = []; p.on('pageerror', e => errs.push(e.message.slice(0, 120)));
    await p.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' });
    await p.waitForTimeout(800);
    await p.screenshot({ path: process.env.SP + '/home-' + name + '.png', fullPage: true });
    const makeOpts = await p.$$eval('#nv-make option', o => o.length);
    console.log(name, 'full-page shot taken; make options loaded:', makeOpts, errs.length ? 'JS ERRORS: ' + errs.join(' | ') : 'no JS errors');
    await p.close();
  }
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
