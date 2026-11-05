const { chromium } = require(process.env.PWC); const fs=require('fs');
const OUT = process.env.HOME + '/pti-work/reel/shots/';
const ck = JSON.parse(fs.readFileSync(process.env.HOME+'/pti-work/reel/cookie.json'));
const clean = `()=>{document.querySelectorAll('[data-aitr],.floaty,.aitr,#onetrust-banner-sdk,.cookie,.ua-launch~*[hidden]').forEach(e=>e.remove());}`;
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:2, isMobile:true, hasTouch:true });
  await ctx.addCookies([
    { name: ck.name, value: ck.value, domain:'dev.udshafts.com', path:'/', httpOnly:true, secure:true },
    { name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' },
  ]);
  const p = await ctx.newPage();
  const shot = async (url, file, {wait=1200, scrollY=0, evalFn=null, full=false}={}) => {
    await p.goto('https://dev.udshafts.com'+url, { waitUntil:'networkidle' }).catch(()=>{});
    await p.waitForTimeout(wait);
    await p.evaluate(clean).catch(()=>{});
    if (scrollY) { await p.evaluate(y=>scrollTo(0,y), scrollY); await p.waitForTimeout(500); }
    if (evalFn) { await evalFn(p); await p.waitForTimeout(800); }
    await p.screenshot({ path: OUT+file+'.png', fullPage: full });
    console.log(file, 'ok');
  };
  // 1 home hero
  await shot('/', 'home', {wait:1600});
  // 2 complete driveshafts by vehicle
  await shot('/catalog/driveshafts', 'complete', {wait:1600});
  // 3 shop / catalog
  await shot('/shop', 'shop', {wait:1400, scrollY:420});
  // 4 driveshaft designer — load a drawing then type a dimension so values show
  await shot('/driveshaft-designer/1/01+01R', 'designer', {wait:1800, evalFn: async(pg)=>{
    try { await pg.evaluate(()=>{ location.hash='#C1=52.25&W1=48.5&T1=3.5'; }); } catch(e){}
    await pg.waitForTimeout(600);
    const el = await pg.$('.dsg__svg, .dsg, #dsg-svg, svg'); if (el) await el.scrollIntoViewIfNeeded().catch(()=>{});
  }});
  // 5 UDS Assistant — open widget (auth'd as user 12)
  await p.goto('https://dev.udshafts.com/shop', { waitUntil:'networkidle' }); await p.waitForTimeout(1200);
  await p.evaluate(clean).catch(()=>{});
  const launch = await p.$('.ua-launch'); if (launch) { await launch.click(); await p.waitForTimeout(1800); }
  await p.screenshot({ path: OUT+'assistant.png' }); console.log('assistant ok');
  // 6 My Account dashboard
  await shot('/account', 'account', {wait:1600});
  await b.close();
})().catch(e=>{console.error('CAP FAIL', e.message); process.exit(1);});
