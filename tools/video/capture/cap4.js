const { chromium } = require(process.env.PWC); const fs=require('fs');
const OUT = process.env.HOME + '/pti-work/reel/shots/';
const ck = JSON.parse(fs.readFileSync(process.env.HOME+'/pti-work/reel/cookie.json'));
const cards = JSON.parse(fs.readFileSync(process.env.HOME+'/pti-work/reel/demo-cards.json')).map(c => ({...c, name: c.name.replace(/&amp;/g,'&').replace(/ /g,' ')}));
const HIDE = `.floaty,.ua-launch,[data-aitr],.aitr{display:none!important}`;
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:2, isMobile:true, hasTouch:true });
  await ctx.addCookies([{ name: ck.name, value: ck.value, domain:'dev.udshafts.com', path:'/', httpOnly:true, secure:true }, { name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage();
  // ---- assistant: staged demo of real capabilities with real catalog parts ----
  await p.goto('https://dev.udshafts.com/shop', { waitUntil:'networkidle' }); await p.waitForTimeout(1000);
  await p.click('.ua-launch'); await p.waitForTimeout(1800);
  await p.addStyleTag({content:HIDE});
  await p.evaluate((cards) => {
    const log = document.querySelector('[data-ua-log]'); log.innerHTML = '';
    const esc = (s) => { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; };
    const msg = (who, html) => { const d=document.createElement('div'); d.className='ua-msg ua-msg--'+who; d.innerHTML=html; log.appendChild(d); };
    msg('me', esc('I need a 1350 u-joint for my lifted Jeep. What do you have?'));
    msg('bot', 'Here are the <strong>1350-series u-joints in stock</strong> right now. The 5-1350XU is our heavy-duty sealed pick, and the 1350TST is built for high angles on lifted builds.');
    const row=document.createElement('div'); row.className='ua-cards';
    cards.forEach(c => { const a=document.createElement('a'); a.className='ua-card'; a.href='#';
      a.innerHTML='<div class="ua-card__img" style="background-image:url(\''+c.image+'\')"></div><div class="ua-card__body"><span class="ua-card__sku">'+esc(c.sku)+'</span><span class="ua-card__name">'+esc(c.name)+'</span><span class="ua-card__foot"><span class="ua-card__price">$'+c.price_usd.toFixed(2)+'</span><span class="ua-card__stock ua-card__stock--in">In stock</span></span></div>';
      row.appendChild(a); });
    log.appendChild(row);
    msg('me', esc('Add the 1350TST to my cart, please.'));
    msg('bot', 'Done: <strong>1 × 1350TST</strong> is in your cart. Order before 2pm ET and it ships today.');
    const inp=document.querySelector('[data-ua-input]'); if(inp) inp.value='';
    log.scrollTop = 0;
  }, cards);
  await p.waitForTimeout(1500);
  console.log('assistant skipped');
  // ---- account: anonymized demo customer ----
  await p.evaluate(() => { try { sessionStorage.removeItem('ua.open'); } catch (e) {} });
  await p.goto('https://dev.udshafts.com/account', { waitUntil:'networkidle' }); await p.addStyleTag({content:HIDE + '.ua,.ua-panel{display:none!important}'}); await p.waitForTimeout(1200);
  await p.evaluate(() => {
    const rep = [[/federico schwindt/gi,'Alex Morgan'],[/owner@example\.com/gi,'alex@example.com'],[/federico/gi,'Alex'],[/^FS$/,'AM']];
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
    while ((n = w.nextNode())) { let t = n.nodeValue; rep.forEach(([a,b2]) => { t = t.replace(a,b2); }); if (t.trim()==='FS') t='AM'; n.nodeValue = t; }
  });
  await p.waitForTimeout(400);
  await p.screenshot({ path: OUT+'account.png' }); console.log('account ok');
  await b.close();
})().catch(e=>{console.error('FAIL', e.message); process.exit(1);});
