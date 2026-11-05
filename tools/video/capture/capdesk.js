const { chromium } = require(process.env.PWC); const fs = require('fs');
const OUT = process.env.HOME + '/pti-work/reel/code/video/shots/'; const ck = require('./cookie.json');
const cards = JSON.parse(fs.readFileSync('./demo-cards.json')).map(c => ({ ...c, name: c.name.replace(/&amp;/g, '&') }));
const HIDE = `.floaty,[data-aitr],.aitr,[href^="/admin"]{display:none!important} *{caret-color:transparent}`;
const meta = {};
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  await ctx.addCookies([{ name: ck.name, value: ck.value, domain: 'dev.udshafts.com', path: '/', httpOnly: true, secure: true }, { name: 'uds_lang', value: 'en', domain: 'dev.udshafts.com', path: '/' }]);
  const p = await ctx.newPage();
  const anon = () => p.evaluate(() => {
    const rep = [[/federico schwindt/gi, 'Alex Morgan'], [/owner@example\.com/gi, 'alex@example.com'], [/federico|leonel/gi, 'Alex'], [/schwindt/gi, 'Morgan']];
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
    while ((n = w.nextNode())) { let t = n.nodeValue; rep.forEach(([a, c]) => { t = t.replace(a, c); }); if (t.trim() === 'FS') t = t.replace('FS', 'AM'); n.nodeValue = t; }
  });
  const go = async (u) => { await p.goto('https://dev.udshafts.com' + u, { waitUntil: 'load', timeout: 60000 }); await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await p.addStyleTag({ content: HIDE }); await anon(); await p.mouse.move(1439, 899); await p.waitForTimeout(900); };
  const center = async (loc) => { const bb = await loc.boundingBox(); return bb ? [Math.round(bb.x + bb.width / 2), Math.round(bb.y + bb.height / 2)] : null; };
  const shot = async (name, extra = {}) => { await p.screenshot({ path: OUT + name + '.png' }); meta[name] = extra; console.log('shot', name, JSON.stringify(extra)); };

  // empty the cart so the demo order holds one shaft
  await go('/cart'); for (let j = 0; j < 12; j++) { const r = p.locator('button:has-text("Remove"), a:has-text("Remove")').first(); if (!await r.count()) break; await r.click(); await p.waitForTimeout(1500); }

  // ---- HOME + SEARCH
  await go('/');
  const open = p.locator('[data-open-search]').first();
  await shot('home', { click: await center(open) });
  await open.click(); await p.waitForTimeout(800); await p.mouse.move(1439, 899);
  const inp = p.locator('form.usearch input[type=search]').first();
  await shot('search-0');
  for (const [i, ch] of [...'1310'].entries()) { await inp.type(ch); await p.waitForTimeout(i === 3 ? 2000 : 300); await shot('search-' + (i + 1)); }
  const row = p.locator('.usearch-results a, [class*="sres"] a, [role="option"]').first();
  meta['search-4'].hover = await center(row).catch(() => null);

  // ---- FINDER
  await go('/catalog/driveshafts');
  const steps = ['Ford', '2015', 'F150', 'AWD/4WD', 'Front'];
  await shot('finder-0');
  for (const [i, v] of steps.entries()) {
    const t = p.locator(`.fdr-tile[data-val="${v}"]`).first(); await t.scrollIntoViewIfNeeded(); await p.waitForTimeout(300);
    meta['finder-' + i].click = await center(t); meta['finder-' + i].scroll = await p.evaluate(() => scrollY);
    await t.click(); await p.mouse.move(1439, 899); await p.waitForTimeout(2300); await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(400);
    await shot('finder-' + (i + 1));
  }
  // remaining facet (engine) if any, then the result row
  for (let j = 0; j < 2 && await p.locator('.fdr-tile').count(); j++) { const t = p.locator('.fdr-tile').last(); const k = Object.keys(meta).filter(n => n.startsWith('finder-')).length - 1; meta['finder-' + k].click = await center(t); await t.click(); await p.mouse.move(1439, 899); await p.waitForTimeout(2300); await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(400); await shot('finder-' + (k + 1)); }
  const add = p.locator('[data-add]').first(); await add.scrollIntoViewIfNeeded(); await p.waitForTimeout(400);
  const sy = await p.evaluate(() => scrollY); await shot('finder-res', { click: await center(add), scroll: sy });
  await add.click(); await p.mouse.move(1439, 899); await p.waitForTimeout(2200); await shot('finder-added');

  // ---- ASSISTANT (desktop panel)
  await go('/shop');
  const la = p.locator('.ua-launch'); await shot('as-0', { click: await center(la) });
  await la.click(); await p.waitForTimeout(1800); await p.addStyleTag({ content: '.ua-launch{display:none!important}' }); await p.mouse.move(1439, 899);
  await p.evaluate(() => { document.querySelector('[data-ua-log]').innerHTML = ''; });
  const ai = p.locator('[data-ua-input]'); meta['as-0'].input = await center(ai);
  await shot('as-1');
  const q = 'I need a 1350 u-joint for my lifted Jeep. Can you ship it today?';
  for (let i = 4, k = 0; i <= q.length + 3; i += 4, k++) { await ai.evaluate((e, v) => { e.value = v; }, q.slice(0, i)); await shot('as-t' + String(k).padStart(2, '0')); }
  const H = `const log=document.querySelector('[data-ua-log]'); const esc=(s)=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;}; const msg=(who,html)=>{const d=document.createElement('div');d.className='ua-msg ua-msg--'+who;d.innerHTML=html;log.appendChild(d);log.scrollTop=log.scrollHeight;};`;
  await p.evaluate(new Function('q', H + `msg('me', esc(q)); document.querySelector('[data-ua-input]').value='';`), q); await p.waitForTimeout(300); await shot('as-2');
  await p.evaluate(new Function(H + `msg('bot','<span class="ua-typing"><i></i><i></i><i></i></span>');`)); await shot('as-3');
  await p.evaluate(new Function('cards', H + `log.lastChild.remove(); msg('bot','Here are the <strong>1350-series u-joints in stock</strong> right now. The 5-1350XU is our heavy-duty sealed pick, and the 1350TST is built for high angles on lifted builds. Order before 2pm ET and it <strong>ships today</strong>.');
    const row=document.createElement('div'); row.className='ua-cards';
    cards.forEach(c=>{const a=document.createElement('a'); a.className='ua-card'; a.href='#'; a.innerHTML='<div class="ua-card__img" style="background-image:url(\\''+c.image+'\\')"></div><div class="ua-card__body"><span class="ua-card__sku">'+esc(c.sku)+'</span><span class="ua-card__name">'+esc(c.name)+'</span><span class="ua-card__foot"><span class="ua-card__price">$'+c.price_usd.toFixed(2)+'</span><span class="ua-card__stock ua-card__stock--in">In stock</span></span></div>'; row.appendChild(a);});
    log.appendChild(row); log.scrollTop=0;`), cards); await p.waitForTimeout(1600); await shot('as-4');

  // ---- ACCOUNT (desktop)
  await go('/account'); await shot('account');
  fs.writeFileSync(OUT + 'meta.json', JSON.stringify(meta, null, 1));
  await b.close();
})().catch(e => { console.error('FAIL', e.message); fs.writeFileSync(OUT + 'meta.json', JSON.stringify(meta, null, 1)); process.exit(1); });
