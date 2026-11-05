const { chromium } = require(process.env.PWC);
(async () => { const S = process.env.S; const b = await chromium.launch({ headless: true }); const ctx = await b.newContext({ viewport: { width: 720, height: 900 }, deviceScaleFactor: 1 }); const page = await ctx.newPage();
  for (const t of ['order-confirmation', 'pay-link', 'newsletter-welcome', 'order-shipped']) { await page.goto('file://' + S + '/prev-' + t + '.html', { waitUntil: 'networkidle' }); await page.screenshot({ path: S + '/shots/mail-' + t + '.png', fullPage: true }); }
  await b.close(); })();
