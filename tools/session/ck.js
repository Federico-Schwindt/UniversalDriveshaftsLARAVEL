const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  for (const [name, vp, mobile] of [['desktop', {width:1366,height:900}, false], ['phone', {width:390,height:844}, true]]) {
    const ctx = await b.newContext({ viewport: vp, deviceScaleFactor: mobile ? 2 : 1, isMobile: mobile, hasTouch: mobile });
    const p = await ctx.newPage();
    await p.goto('https://dev.udshafts.com/shop', { waitUntil: 'domcontentloaded' });
    const r = await p.evaluate(async () => {
      const t = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const res = await fetch('/cart/add', { method: 'POST', headers: { 'X-CSRF-TOKEN': t, 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: 'product_id=69554&quantity=1' });
      return res.status;
    });
    console.log(name, 'cart/add', r);
    await p.goto('https://dev.udshafts.com/checkout', { waitUntil: 'networkidle', timeout: 60000 });
    const card = await p.$('.cocard');
    await card.screenshot({ path: `${process.argv[2]}-${name}.png` });
    const info = await p.evaluate(() => { const l = document.querySelector('#sms-opt-in').closest('label'); const cs = getComputedStyle(l); return { display: cs.display, h: l.getBoundingClientRect().height, w: l.getBoundingClientRect().width }; });
    console.log(name, JSON.stringify(info));
    await ctx.close();
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
