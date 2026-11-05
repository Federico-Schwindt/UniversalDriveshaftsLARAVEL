const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage();
  await p.goto('https://c2.powertrainindustries.com/auth', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1200);
  await p.fill('input[name="username"]', process.env.PTI_USER); await p.fill('input[name="password"]', process.env.PTI_PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), p.click('button[type="submit"]')]);
  await p.goto('https://c2.powertrainindustries.com/buy/checkout', { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(2500);
  const info = await p.evaluate(() => ({
    opts: [...document.querySelectorAll('select[name=ship_from_store_id],select[name=billing_method],select[name=cim_payment_profile_id],select[name=is_signature_required]')].map(e=>[e.name,[...e.options].map(o=>[o.value,o.textContent.trim(),o.dataset.lastFour||''])]), fields: [...document.querySelectorAll('input,select,textarea')].map(e => [e.tagName, e.name || e.id, e.type, (e.closest('label,.form-group,div')?.innerText || '').replace(/\s+/g, ' ').slice(0, 70)]).filter(x => x[1]),
    text: document.body.innerText.replace(/\n+/g, ' / ').slice(0, 2500),
    inline: [...document.scripts].filter(s => !s.src).map(s => s.textContent).join('\n').slice(0, 200),
    srcs: [...document.scripts].map(s => s.src).filter(Boolean),
  }));
  fs.writeFileSync(process.env.OUT + '/checkout.json', JSON.stringify(info, null, 1));
  for (const u of info.srcs) { if (/powertrain/.test(u)) { const r = await ctx.request.get(u); fs.writeFileSync(process.env.OUT + '/ck-' + u.split('/').pop().split('?')[0], await r.text()); } }
  await b.close();
})().catch(e => { console.error('FAILED', e.message); process.exit(1); });
