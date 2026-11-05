const { chromium } = require(process.env.PWC);
const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ viewport: { width: 1280, height: 1000 } });
  const jar = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
  await ctx.addCookies(jar);
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(String(e)));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 200)); });
  await p.goto(process.argv[2], { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(7000);
  const r = await p.evaluate(() => {
    const host = document.getElementById('stripe-card');
    const frames = host ? host.querySelectorAll('iframe').length : 0;
    return { mounted: frames > 0, iframes: frames, height: host ? Math.round(host.getBoundingClientRect().height) : 0,
      hasValidate: typeof window.udsStripeValidate === 'function', hasAmount: typeof window.udsStripeAmount === 'function',
      totalCents: window.UDS_TOTAL_CENTS };
  });
  console.log(JSON.stringify(r));
  console.log('ERRORS:', errs.length ? errs.slice(0,6) : 'none');
  const el = await p.$('#stripe-card');
  if (el) await el.screenshot({ path: process.argv[4] });
  await b.close();
})();
