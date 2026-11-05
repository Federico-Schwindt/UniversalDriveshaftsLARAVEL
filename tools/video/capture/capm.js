const { chromium } = require(process.env.PWC); const fs = require('fs');
const OUT = process.env.HOME + '/pti-work/reel/cap2/'; const ck = require('./cookie.json');
const meta = {}; const HIDE = `.fdr-pic:hover,.fdr-pic{transform:none!important;position:static!important} .floaty,.ua-launch,[data-aitr],.aitr,.admin-bar,.adminbar,[href^="/admin"]{display:none!important}`;
const anon = (p) => p.evaluate(() => {
  const rep = [[/federico schwindt/gi,'Alex Morgan'],[/owner@example\.com/gi,'alex@example.com'],[/federico|leonel/gi,'Alex'],[/schwindt/gi,'Morgan']];
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
  while ((n = w.nextNode())) { let t = n.nodeValue; rep.forEach(([a,b]) => { t = t.replace(a,b); }); if (t.trim()==='FS') t = t.replace('FS','AM'); n.nodeValue = t; }
  document.querySelectorAll('.tel__code').forEach(e => { e.textContent = '+1'; }); document.querySelectorAll('.tel__flag img, img.tel__flag').forEach(i => { i.src = i.src.replace(/\/[a-z]{2}(\.[a-z]+)$/i, '/us$1'); });
  document.querySelectorAll('select').forEach(sel => { const o = [...sel.options].find(o => /\+1\b/.test(o.text) && /US|United States|🇺🇸/.test(o.text + o.value)); if (o) { sel.value = o.value; sel.dispatchEvent(new Event('change', { bubbles: true })); } });
  document.querySelectorAll('input[type=tel]').forEach(i => { i.value = '(813) 555-0142'; });
  document.querySelectorAll('input').forEach(i => { if (/leonel|federico|schwindt/i.test(i.value)) i.value = i.value.replace(/owner@example\.com/i,'alex@example.com').replace(/federico/i,'Alex').replace(/schwindt/i,'Morgan'); });
});
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:3, isMobile:true, hasTouch:true });
  await ctx.addCookies([{ name:ck.name, value:ck.value, domain:'dev.udshafts.com', path:'/', httpOnly:true, secure:true }, { name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage();
  let k = 0; const shot = async (name, tapSel) => {
    let tap = null;
    if (tapSel) { const bb = await (typeof tapSel === 'string' ? p.locator(tapSel).first() : tapSel).boundingBox(); if (bb) tap = { x: bb.x + bb.width/2, y: bb.y + bb.height/2 }; }
    await p.screenshot({ path: OUT + name + '.png' }); meta[name] = { tap, i: k++ }; console.log('shot', name, tap ? `${tap.x|0},${tap.y|0}` : '');
  };
  const go = async (u) => { await p.goto('https://dev.udshafts.com' + u, { waitUntil:'load', timeout:60000 }); await p.waitForLoadState('networkidle',{timeout:8000}).catch(()=>{}); await p.addStyleTag({ content: HIDE }); await anon(p); await p.waitForTimeout(900); };
  // HOME
  await go('/');
  await shot('home-0');
  await p.screenshot({ path: OUT + 'home-full.png', fullPage: true }); console.log('home full');
  // SEARCH typing
  await shot('search-0', '[data-open-search]');
  await p.click('[data-open-search]'); await p.waitForTimeout(700);
  const inp = p.locator('form.usearch input[type=search]').first();
  await shot('search-1');
  for (const [i, ch] of [...'1310'].entries()) { await inp.type(ch); await p.waitForTimeout(i === 3 ? 1800 : 250); await shot('search-t' + (i+1)); }
  // empty the demo cart first so the order shows one shaft
  await go('/cart'); for (let j = 0; j < 12; j++) { const r = p.locator('button:has-text("Remove"), a:has-text("Remove")').first(); if (!await r.count()) break; await r.click(); await p.waitForTimeout(1500); }
  // FINDER
  await go('/catalog/driveshafts'); await p.waitForTimeout(1200);
  await shot('finder-0');
  for (const [i, v] of ['Ford','2015','F150'].entries()) {
    const t = p.locator(`.fdr-tile[data-val="${v}"]`).first(); await t.scrollIntoViewIfNeeded(); await p.waitForTimeout(300);
    await shot('finder-' + (i+1) + 'a', t); await t.click(); await p.mouse.move(398, 4); await p.waitForTimeout(2300); await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(300); await shot('finder-' + (i+1) + 'b');
  }
  // remaining facets: click first tile until results
  for (let j = 0; j < 3 && await p.locator('.fdr-tile').count(); j++) { const t = p.locator('.fdr-tile').first(); await shot('finder-x' + j + 'a', t); await t.click(); await p.mouse.move(398, 4); await p.waitForTimeout(2300); await shot('finder-x' + j + 'b'); }
  const res = await p.locator('#fdr-table').first().boundingBox().catch(() => null);
  if (res) { await p.evaluate((y) => window.scrollTo(0, y - 90), res.y + await p.evaluate(() => scrollY)); await p.waitForTimeout(500); await shot('finder-res', '[data-add]'); }
  // add to cart -> drawer
  const add = p.locator('[data-add]').first();
  if (await add.count()) { await add.click(); await p.waitForTimeout(1800); await shot('finder-added'); }
  // PRODUCT page of the matched shaft
  const href = await p.locator('#fdr-table a[href*="/product"], #fdr-table a[href*="/shop/"]').first().getAttribute('href').catch(() => null);
  console.log('product href', href);
  if (href) { await go(href.replace('https://dev.udshafts.com','')); await shot('product'); await p.screenshot({ path: OUT + 'product-full.png', fullPage: true }); }
  // CART + CHECKOUT
  await go('/cart'); await shot('cart');
  await go('/checkout'); await shot('checkout');
  await p.screenshot({ path: OUT + 'checkout-full.png', fullPage: true });
  // ACCOUNT
  await go('/account'); await shot('account'); await p.screenshot({ path: OUT + 'account-full.png', fullPage: true });
  fs.writeFileSync(OUT + 'meta.json', JSON.stringify(meta, null, 1));
  await b.close();
})().catch(e => { console.error('FAIL', e.message); fs.writeFileSync(OUT + 'meta.json', JSON.stringify(meta, null, 1)); process.exit(1); });
