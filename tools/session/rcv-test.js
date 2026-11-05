const { chromium } = require(process.env.PWC);
const S = process.env.S, B = 'https://dev.udshafts.com', TOKEN = process.env.TOKEN;
(async () => {
  const b = await chromium.launch({ headless: true });
  const log = (...a) => console.log(...a);
  async function ctx(mobile) {
    const c = await b.newContext(mobile ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: 1366, height: 900 } });
    const p = await c.newPage();
    p.on('pageerror', e => log('PAGEERROR', e.message));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|googleapis|gstatic|facebook|google-analytics|googletagmanager|tiktok/.test(m.text())) log('CONSOLE', m.text().slice(0, 160)); });
    return [c, p];
  }
  async function clipShot(p, sel, name, pad = 24) {
    const el = p.locator(sel).first(); await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(300);
    const bb = await el.boundingBox(); const vp = p.viewportSize();
    const sy = await p.evaluate(() => window.scrollY);
    await p.screenshot({ path: `${S}/${name}.png`, clip: { x: Math.max(0, bb.x - pad), y: Math.max(0, bb.y + sy - pad), width: Math.min(vp.width, bb.width + pad * 2), height: bb.height + pad * 2 }, fullPage: true });
    log('shot', name, Math.round(bb.width) + 'x' + Math.round(bb.height));
  }
  for (const mobile of [false, true]) {
    const tag = mobile ? 'm' : 'd';
    const [c, p] = await ctx(mobile);
    // 1. PDP notify block
    await p.goto(B + '/parts/5-10211X', { waitUntil: 'networkidle' });
    log(tag, 'notify form present:', await p.locator('form.rcv-notify').count());
    await clipShot(p, '.pdp__pricerow ~ .rcv-notify, .rcv-notify', `pdp-notify-${tag}`, mobile ? 10 : 40);
    if (!mobile) {
      await p.locator('form.rcv-notify input[name=email]').fill('test-recovery@uds.local');
      await p.locator('form.rcv-notify button[type=submit]').click();
      await p.waitForSelector('.rcv-notify__msg:not([hidden])', { timeout: 10000 });
      log(tag, 'notify msg:', await p.locator('.rcv-notify__msg').innerText());
      await clipShot(p, '.rcv-notify', `pdp-notify-done-${tag}`, 40);
    }
    // 2. Finder table: quote buttons + modal
    await p.goto(B + '/catalog/driveshafts?make=Toyota&year=1984&model=Celica&trans=Manual&trim=GTS+Liftback&differential=Without+Axle+Code+F382', { waitUntil: 'networkidle' });
    await p.waitForSelector('[data-quote]', { timeout: 20000 });
    log(tag, 'quote buttons in table:', await p.locator('[data-quote]').count(), 'vehicle attr:', await p.locator('[data-quote]').first().getAttribute('data-vehicle'));
    await clipShot(p, '#fdr-table', `finder-table-${tag}`, 12);
    await p.locator('[data-quote]').first().click();
    await p.waitForSelector('.rcv-modal.on', { timeout: 5000 }); await p.waitForTimeout(400);
    log(tag, 'modal title:', await p.locator('#rcv-q-title').innerText(), '| vehicle prefilled:', await p.locator('#rcv-q-vehicle').inputValue());
    await p.screenshot({ path: `${S}/quote-modal-${tag}.png` });
    if (!mobile) {
      await p.fill('#rcv-q-email', 'test-recovery@uds.local'); await p.fill('#rcv-q-phone', '8135550100'); await p.fill('#rcv-q-note', 'Playwright test from staging - ignore');
      const [resp] = await Promise.all([p.waitForResponse(r => r.url().endsWith('/alerts/quote')), p.click('.rcv-qform button[type=submit]')]);
      log(tag, 'quote POST', resp.status(), (await resp.text()).slice(0, 220));
      await p.waitForTimeout(500);
      await p.screenshot({ path: `${S}/quote-done-${tag}.png` });
    } else {
      await p.keyboard.press('Escape'); await p.waitForTimeout(300); log(tag, 'modal closed via Esc:', await p.locator('.rcv-modal').isHidden());
    }
    // 3. finder part page
    await p.goto(B + '/catalog/driveshafts/part/2392-707', { waitUntil: 'networkidle' });
    log(tag, 'part page quote buttons:', await p.locator('[data-quote]').count());
    if (!mobile) { await p.locator('[data-quote]').first().click(); await p.waitForSelector('.rcv-modal.on'); await p.waitForTimeout(400); await p.screenshot({ path: `${S}/quote-part-${tag}.png` }); await p.keyboard.press('Escape'); }
    // 4. restore page (fresh session each context)
    await p.goto(B + '/cart/restore/' + TOKEN, { waitUntil: 'networkidle' });
    await p.screenshot({ path: `${S}/restore-${tag}.png`, fullPage: false });
    log(tag, 'restore h1:', await p.locator('h1').first().innerText());
    await c.close();
  }
  // 5. checkout capture flow (desktop): add to cart in-page, go to checkout, type e-mail + phone, watch capture
  {
    const [c, p] = await ctx(false);
    await p.goto(B + '/parts/PM-DS-9113', { waitUntil: 'networkidle' });
    const r = await p.evaluate(async () => { const t = document.querySelector('meta[name=csrf-token]').content; const r = await fetch('/cart/add', { method: 'POST', headers: { 'X-CSRF-TOKEN': t, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=9113&quantity=1' }); return r.status; });
    log('cart add', r);
    await p.goto(B + '/checkout', { waitUntil: 'networkidle' });
    log('checkout url', p.url(), 'recovery script present:', await p.evaluate(() => [...document.scripts].some(s => s.textContent.includes('/cart/recovery/capture'))));
    const waitCap = p.waitForResponse(x => x.url().endsWith('/cart/recovery/capture'), { timeout: 15000 });
    await p.fill('input[name=email]', 'test-browser@uds.local');
    await p.locator('input[type=tel]').first().fill('8135550177');
    await p.locator('input[type=tel]').first().blur(); await p.locator('input[name=email]').focus(); await p.locator('input[name=email]').blur();
    const cap = await waitCap; log('capture POST', cap.status(), await cap.text());
    await p.waitForTimeout(600);
    await c.close();
  }
  // 6. admin page
  {
    const [c, p] = await ctx(false);
    await p.goto(B + '/login?admin=1', { waitUntil: 'networkidle' });
    await p.fill('#l-email', 'rcv-test-admin@uds.local'); await p.fill('#l-pw', process.env.UDS_QA_PASSWORD);
    await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit]')]);
    await p.goto(B + '/admin/recovery', { waitUntil: 'networkidle' });
    log('admin url', p.url(), 'rows:', await p.locator('.adm-table tbody tr').count());
    await p.screenshot({ path: `${S}/admin-recovery.png`, fullPage: true });
    await c.close();
  }
  // 7. e-mail renders
  {
    const c = await b.newContext({ viewport: { width: 720, height: 900 } }); const p = await c.newPage();
    for (const f of ['mail-cart', 'mail-stock', 'mail-quote-shop', 'mail-quote-cust']) { await p.goto('file://' + S + '/' + f + '.html', { waitUntil: 'load' }); await p.waitForTimeout(400); await p.screenshot({ path: `${S}/${f}.png`, fullPage: true }); }
    await c.close();
  }
  await b.close();
})().catch(e => { console.error('FAIL', e); process.exit(1); });
