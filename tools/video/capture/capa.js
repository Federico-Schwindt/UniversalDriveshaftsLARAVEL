const { chromium } = require(process.env.PWC); const fs = require('fs'); const OUT = process.env.HOME + '/pti-work/reel/cap2/';
const ck = require('./cookie.json');
const cards = JSON.parse(fs.readFileSync('./demo-cards.json')).map(c => ({ ...c, name: c.name.replace(/&amp;/g, '&').replace(/ /g, ' ') }));
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:3, isMobile:true, hasTouch:true });
  await ctx.addCookies([{ name:ck.name, value:ck.value, domain:'dev.udshafts.com', path:'/', httpOnly:true, secure:true }, { name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage();
  await p.goto('https://dev.udshafts.com/shop', { waitUntil:'networkidle' }); await p.addStyleTag({content:'.floaty{display:none!important}'}); await p.waitForTimeout(800);
  const lb = await p.locator('.ua-launch').boundingBox(); console.log('launch', JSON.stringify(lb));
  await p.screenshot({ path: OUT + 'a-0.png' });
  await p.click('.ua-launch'); await p.waitForTimeout(1800); await p.addStyleTag({content:'.ua-launch{display:none!important}'});
  await p.evaluate(() => { const l = document.querySelector('[data-ua-log]'); l.innerHTML = ''; });
  await p.screenshot({ path: OUT + 'a-1.png' });
  const q = 'I need a 1350 u-joint for my lifted Jeep. What do you have?';
  const inp = p.locator('[data-ua-input]');
  for (let i = 1; i <= q.length; i += 4) { await inp.evaluate((e, v) => { e.value = v; }, q.slice(0, i)); await p.screenshot({ path: OUT + `a-t${String(i).padStart(2,'0')}.png` }); }
  await inp.evaluate((e, v) => { e.value = v; }, q); await p.screenshot({ path: OUT + 'a-t99.png' });
  const step = (fn, arg) => p.evaluate(fn, arg);
  const H = `const log=document.querySelector('[data-ua-log]'); const esc=(s)=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;}; const msg=(who,html)=>{const d=document.createElement('div');d.className='ua-msg ua-msg--'+who;d.innerHTML=html;log.appendChild(d);log.scrollTop=log.scrollHeight;};`;
  await step(new Function('q', H + `msg('me', esc(q)); document.querySelector('[data-ua-input]').value='';`), q); await p.waitForTimeout(300); await p.screenshot({ path: OUT + 'a-2.png' });
  await step(new Function(H + `msg('bot','<span class="ua-typing"><i></i><i></i><i></i></span>');`)); await p.screenshot({ path: OUT + 'a-3.png' });
  await step(new Function('cards', H + `log.lastChild.remove(); msg('bot','Here are the <strong>1350-series u-joints in stock</strong> right now. The 5-1350XU is our heavy-duty sealed pick, and the 1350TST is built for high angles on lifted builds.');
    const row=document.createElement('div'); row.className='ua-cards';
    cards.forEach(c=>{const a=document.createElement('a'); a.className='ua-card'; a.href='#'; a.innerHTML='<div class="ua-card__img" style="background-image:url(\\''+c.image+'\\')"></div><div class="ua-card__body"><span class="ua-card__sku">'+esc(c.sku)+'</span><span class="ua-card__name">'+esc(c.name)+'</span><span class="ua-card__foot"><span class="ua-card__price">$'+c.price_usd.toFixed(2)+'</span><span class="ua-card__stock ua-card__stock--in">In stock</span></span></div>'; row.appendChild(a);});
    log.appendChild(row); log.scrollTop=0;`), cards); await p.waitForTimeout(1500); await p.screenshot({ path: OUT + 'a-4.png' });
  await step(new Function(H + `msg('me', esc('Add the 1350TST to my cart, please.')); msg('bot','Done: <strong>1 × 1350TST</strong> is in your cart. Order before 2pm ET and it ships today.');`)); await p.waitForTimeout(500); await p.screenshot({ path: OUT + 'a-5.png' });
  await b.close();
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
