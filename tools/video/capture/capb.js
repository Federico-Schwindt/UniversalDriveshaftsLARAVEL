const { chromium } = require(process.env.PWC); const fs = require('fs');
const OUT = process.env.HOME + '/pti-work/reel/code/video/shots/'; const meta = {};
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  await ctx.addCookies([{ name: 'uds_lang', value: 'en', domain: 'dev.udshafts.com', path: '/' }]);
  const p = await ctx.newPage(); p.setDefaultTimeout(6000); await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  await p.addStyleTag({ content: '.floaty,.ua-launch{display:none!important} *{caret-color:transparent}' }); await p.waitForTimeout(1000);
  const vals = ['52.25', '48.5', '3.5', '4', '2'];
  for (let k = 0; k < 14; k++) {
    const sec = p.locator('section[data-step]:visible').first();
    if (!await sec.count()) break;
    await sec.scrollIntoViewIfNeeded().catch(() => {}); await p.evaluate(() => window.scrollBy(0, -140)); await p.waitForTimeout(400);
    const title = (await sec.locator('h2,h3').first().textContent().catch(() => '') || '').trim().slice(0, 50);
    const inputsAny = sec.locator('input:visible:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=search])'); const cardsL = (await inputsAny.count()) ? sec.locator('.__none') : sec.locator('button:visible:not([data-back]):not([data-back-auto]):not(.dsb-btn--secondary):not([data-qty]):not([data-add-btn])'); const inputs = (await cardsL.count()) ? sec.locator('input.__none') : sec.locator('input:visible:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=search])');
    const sy = await p.evaluate(() => scrollY);
    if (await inputs.count()) {
      const n = await inputs.count(); const clicks = [];
      for (let i = 0; i < n; i++) { const bb = await inputs.nth(i).boundingBox(); clicks.push(bb ? [bb.x + bb.width / 2, bb.y + bb.height / 2] : null); }
      await p.screenshot({ path: OUT + `bld-${k}a.png` });
      for (let i = 0; i < n; i++) { await inputs.nth(i).fill(vals[i] || '1'); await p.waitForTimeout(150); await p.screenshot({ path: OUT + `bld-${k}i${i}.png` }); }
      const nx = sec.locator('button:visible:not([data-back])').last();
      const bb = await nx.boundingBox().catch(() => null);
      meta[`bld-${k}`] = { title, inputs: clicks, next: bb ? [bb.x + bb.width / 2, bb.y + bb.height / 2] : null, n, scroll: sy };
      if (bb) await nx.click(); else break;
    } else {
      const pref = sec.locator('button:visible:not([data-back]):not([data-back-auto]):not(.dsb-btn--secondary):not([data-qty]):not([data-add-btn])').filter({ hasText: /custom length|1350 Series|Light|Steel|Front|Weld|Yes|Continue/ }); const opt = (await pref.count()) ? pref.first() : sec.locator('button:visible:not([data-back]):not([data-back-auto]):not(.dsb-btn--secondary):not([data-qty]):not([data-add-btn])').first(); if (!await opt.count()) { console.log('no option in step', await sec.getAttribute('data-step')); break; } const bb = await opt.boundingBox();
      await p.mouse.move(1439, 899); await p.screenshot({ path: OUT + `bld-${k}a.png` });
      meta[`bld-${k}`] = { title, click: bb ? [bb.x + bb.width / 2, bb.y + bb.height / 2] : null, label: ((await opt.textContent()) || '').trim().replace(/\s+/g, ' ').slice(0, 40), scroll: sy };
      await opt.click();
    }
    await p.mouse.move(1439, 899); await p.waitForTimeout(1300);
    console.log(k, JSON.stringify(meta[`bld-${k}`]));
    if (/review/i.test(title) || (await p.locator('section[data-step="6"]:visible').count())) break;
  }
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(500); await p.screenshot({ path: OUT + 'bld-end.png' });
  await p.screenshot({ path: OUT + 'bld-end-full.png', fullPage: true });
  fs.writeFileSync(OUT + 'meta-bld.json', JSON.stringify(meta, null, 1)); await b.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
