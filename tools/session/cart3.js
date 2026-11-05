const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, hasTouch: true, isMobile: true });
  const p = await ctx.newPage(); p.on('pageerror', e => console.log('JSERR', e.message));
  await p.goto('https://dev.udshafts.com/shop/driveshaft-components/u-joints', { waitUntil: 'networkidle' });
  for (const n of [1, 2]) { const plus = p.locator('.p-plus:not([data-has-alt])').nth(n); await plus.scrollIntoViewIfNeeded(); await plus.click(); await p.waitForTimeout(1500); }
  console.log('auto-open?', await p.locator('#ucart.is-open').count(), 'badge', await p.locator('.uhead [data-cart-count]').first().textContent());
  await p.locator('.uhead .iconbtn--cart').click(); await p.waitForTimeout(1500);
  console.log('ask uds visible while open:', await p.locator('.ua-launch:visible').count(), 'floaty:', await p.locator('.floaty:visible').count());
  await p.screenshot({ path: process.env.SP + '/cart3.png' });
  const alt = p.locator('.p-plus[data-has-alt]').first();
  if (await alt.count()) { await p.locator('[data-ucart-close]').last().click(); await p.waitForTimeout(500); await alt.scrollIntoViewIfNeeded(); const before = await p.locator('.uhead [data-cart-count]').first().textContent(); await alt.click(); await p.waitForTimeout(1500);
    console.log('alt chooser open:', await p.locator('#alt-pop.on').count(), '| count unchanged:', before === await p.locator('.uhead [data-cart-count]').first().textContent());
    await p.screenshot({ path: process.env.SP + '/alt.png' }); }
  await b.close(); })().catch(e => { console.error('FAILED', e.message); process.exit(1); });
