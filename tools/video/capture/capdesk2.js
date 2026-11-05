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


  // put one shaft in the cart for checkout
  await go('/cart'); for (let j = 0; j < 12; j++) { const r = p.locator('button:has-text("Remove"), a:has-text("Remove")').first(); if (!await r.count()) break; await r.click(); await p.waitForTimeout(1500); }
  await go('/catalog/driveshafts');
  for (const v of ['Ford', '2015', 'F150', 'AWD/4WD', 'Front']) { await p.locator(`.fdr-tile[data-val="${v}"]`).first().click(); await p.waitForTimeout(1800); }
  if (await p.locator('.fdr-tile').count()) { await p.locator('.fdr-tile').last().click(); await p.waitForTimeout(1800); }
  await p.locator('[data-add]').first().click(); await p.waitForTimeout(1800);
  // ---- ASSISTANT with a measurement question
  await p.evaluate(() => { try { sessionStorage.removeItem('ua.open'); } catch (e) {} });
  await go('/shop');
  const la = p.locator('.ua-launch'); await shot('ai-0', { click: await center(la) });
  await la.click(); await p.waitForTimeout(1800); await p.addStyleTag({ content: '.ua-launch{display:none!important}' }); await p.mouse.move(1439, 899);
  await p.evaluate(() => { document.querySelector('[data-ua-log]').innerHTML = ''; });
  const ai = p.locator('[data-ua-input]'); const ib = await ai.boundingBox(); meta['ai-0'].input = await center(ai); meta['ai-0'].inputBox = ib;
  await shot('ai-1');
  const q = 'I need a universal joint with 1.188" caps and a 3.625" span. What fits?';
  for (let i = 3, k = 0; i <= q.length + 2; i += 3, k++) { await ai.evaluate((e, v) => { e.value = v; e.scrollLeft = 9999; }, q.slice(0, i)); await shot('ai-t' + String(k).padStart(2, '0')); }
  meta['ai-0'].typed = Math.ceil((q.length) / 3);
  const send = p.locator('[data-ua-send], .ua-send, form.ua-form button[type=submit]').first(); meta['ai-0'].send = await center(send).catch(() => null);
  const H = `const log=document.querySelector('[data-ua-log]'); const esc=(s)=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;}; const msg=(who,html)=>{const d=document.createElement('div');d.className='ua-msg ua-msg--'+who;d.innerHTML=html;log.appendChild(d);log.scrollTop=log.scrollHeight;};`;
  await p.evaluate(new Function('q', H + `msg('me', esc(q)); document.querySelector('[data-ua-input]').value='';`), q); await p.waitForTimeout(300); await shot('ai-2');
  await p.evaluate(new Function(H + `msg('bot','<span class="ua-typing"><i></i><i></i><i></i></span>');`)); await shot('ai-3');
  await p.evaluate(new Function('cards', H + `log.lastChild.remove(); msg('bot','1.188&quot; caps with a 3.625&quot; span is a <strong>1350-series</strong> joint. These are in stock right now. Order before 2pm ET and it <strong>ships today</strong>.');
    const row=document.createElement('div'); row.className='ua-cards';
    cards.forEach(c=>{const a=document.createElement('a'); a.className='ua-card'; a.href='#'; a.innerHTML='<div class="ua-card__img" style="background-image:url(\\''+c.image+'\\')"></div><div class="ua-card__body"><span class="ua-card__sku">'+esc(c.sku)+'</span><span class="ua-card__name">'+esc(c.name)+'</span><span class="ua-card__foot"><span class="ua-card__price">$'+c.price_usd.toFixed(2)+'</span><span class="ua-card__stock ua-card__stock--in">In stock</span></span></div>'; row.appendChild(a);});
    log.appendChild(row); log.scrollTop=0;`), cards); await p.waitForTimeout(1800); await shot('ai-4');
  const pb = await p.locator('[data-ua-log]').boundingBox(); meta['ai-4'].log = pb;
  // ---- CHECKOUT
  await p.evaluate(() => { try { sessionStorage.removeItem('ua.open'); } catch (e) {} });
  await go('/cart'); await p.addStyleTag({ content: '.ua,.ua-panel{display:none!important}' }); const co = p.locator('a:has-text("Checkout"), button:has-text("Checkout")').first(); await shot('cart', { click: await center(co) });
  await go('/checkout'); await p.addStyleTag({ content: '.ua,.ua-panel{display:none!important}' });
  await p.evaluate(() => { document.querySelectorAll('.tel__code').forEach(e => { e.textContent = '+1'; }); document.querySelectorAll('.tel__flag img, img.tel__flag').forEach(i => { i.src = i.src.replace(/\/[a-z]{2}(\.[a-z]+)$/i, '/us$1'); }); document.querySelectorAll('input[type=tel]').forEach(i => { i.value = '(813) 555-0142'; }); });
  await shot('checkout'); await p.screenshot({ path: OUT + 'checkout-full.png', fullPage: true });
  const place = p.locator('button:has-text("place order"), button:has-text("Place order"), button:has-text("Review")').first(); meta['checkout'].click = await center(place).catch(() => null);
  // ---- ACCOUNT without the assistant panel
  await go('/account'); await p.addStyleTag({ content: '.ua,.ua-panel,.ua-launch{display:none!important}' }); await p.waitForTimeout(300); await shot('account');
  fs.writeFileSync(OUT + 'meta2.json', JSON.stringify(meta, null, 1));
  await b.close();
})().catch(e => { console.error('FAIL', e.message); fs.writeFileSync(OUT + 'meta2.json', JSON.stringify(meta, null, 1)); process.exit(1); });
