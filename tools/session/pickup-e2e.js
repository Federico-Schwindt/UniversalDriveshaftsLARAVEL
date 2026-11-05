const { chromium } = require(process.env.PWC);
(async () => {
  const out = {}, errs = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } }); // phone
  page.on('pageerror', e => errs.push('PAGEERROR ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errs.push('CONSOLE ' + m.text().slice(0, 160)); });
  await page.goto('https://dev.udshafts.com/cart');
  const tok = await page.evaluate(() => document.querySelector('meta[name=csrf-token]').content);
  out.addToCart = await page.evaluate(async t => (await fetch('/shop/catalog/add', { method: 'POST', headers: { 'X-CSRF-TOKEN': t, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=11377&qty=1' })).status, tok);
  await page.goto('https://dev.udshafts.com/checkout');
  await page.evaluate(() => { window.__alerts = []; window.alert = m => window.__alerts.push(m); });
  const q = s => page.evaluate(s => { const e = document.querySelector(s); return e ? { hidden: e.hidden, text: e.textContent.trim().slice(0, 90), value: e.value } : null; }, s);
  // 1) New York customer enters address
  await page.fill('#billing_state', 'NY'); await page.fill('[name=billing_postcode]', '10001');
  await page.locator('[name=billing_postcode]').dispatchEvent('blur');
  await page.waitForFunction(() => document.querySelectorAll('#ship-select option').length > 3, null, { timeout: 40000 });
  out.afterRates = await page.evaluate(() => { const s = document.getElementById('ship-select'); const o = [...s.options].map(x => x.value).filter(Boolean); return { selected: s.value, lastOption: o[o.length - 1], count: o.length }; });
  out.pickupBoxBefore = (await q('#pickup-box')).hidden;
  // 2) picks pickup
  await page.selectOption('#ship-select', 'local_pickup');
  out.pickupBox = await q('#pickup-box'); out.farWarning = await q('#pickup-far'); out.farState = (await q('#pickup-far-state')).text;
  out.metaLabels = await page.evaluate(() => [...document.querySelectorAll('#meta-from-l,#meta-date-l,#meta-date-v,#meta-date-s')].map(e => e.textContent));
  out.payOptions = await page.evaluate(() => [...document.getElementById('pay-method').options].map(o => o.value));
  out.sumShip = (await q('#sum-ship')).text;
  // 3) pay at pickup
  await page.selectOption('#pay-method', 'pay_at_pickup'); out.payNote = await q('#pay-pickup-note');
  // 4) tries to place WITHOUT acknowledging -> must be blocked client-side (no request sent)
  let placeCalls = 0; page.on('request', r => { if (r.url().endsWith('/checkout/place')) placeCalls++; });
  await page.check('#agree-terms'); await page.click('#place-btn'); await page.waitForTimeout(400);
  out.alerts = await page.evaluate(() => window.__alerts); out.placeRequestsSent = placeCalls;
  // 5) acknowledges -> screenshot the state (do NOT place: it would create a real order)
  await page.check('#pickup-ack');
  await page.locator('#pickup-box').scrollIntoViewIfNeeded();
  await page.screenshot({ path: process.env.SP + '/pickup-mobile.png', fullPage: false });
  // 6) switches back to UPS -> pickup UI must fully retract
  await page.selectOption('#ship-select', out.afterRates.selected);
  out.afterBack = { box: (await q('#pickup-box')).hidden, ack: await page.isChecked('#pickup-ack'), pay: (await q('#pay-method')).value, payOptions: await page.evaluate(() => [...document.getElementById('pay-method').options].map(o => o.value)) };
  out.errors = errs;
  console.log(JSON.stringify(out, null, 1));
  await browser.close();
})().catch(e => { console.error('FAILED', e.message); process.exit(1); });
