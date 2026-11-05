const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  const [out, url, ...ys] = process.argv.slice(2);
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const p = await ctx.newPage(); await p.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
  for (const y of ys) { await p.evaluate(v => window.scrollTo(0, v), Number(y)); await p.waitForTimeout(300); await p.screenshot({ path: `${out}/g-phone-${y}.png` }); }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
