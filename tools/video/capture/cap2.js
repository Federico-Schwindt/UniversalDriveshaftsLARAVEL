const { chromium } = require(process.env.PWC); const fs=require('fs');
const OUT = process.env.HOME + '/pti-work/reel/shots/';
const ck = JSON.parse(fs.readFileSync(process.env.HOME+'/pti-work/reel/cookie.json'));
const HIDE = `.floaty,.ua-launch,[data-aitr],.aitr,.ua-fab,.ua{display:none!important}`;
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:2, isMobile:true, hasTouch:true });
  await ctx.addCookies([
    { name: ck.name, value: ck.value, domain:'dev.udshafts.com', path:'/', httpOnly:true, secure:true },
    { name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' },
  ]);
  await ctx.addInitScript(css=>{ const s=document.createElement('style'); s.textContent=css; document.documentElement.appendChild(s); }, HIDE);
  const p = await ctx.newPage();
  const go = async (url, {wait=1500, scrollY=0}={}) => {
    await p.goto('https://dev.udshafts.com'+url, { waitUntil:'networkidle' }).catch(()=>{});
    await p.addStyleTag({content:HIDE}).catch(()=>{});
    await p.waitForTimeout(wait);
    if (scrollY) { await p.evaluate(y=>scrollTo(0,y), scrollY); await p.waitForTimeout(600); }
  };
  await go('/'); await p.screenshot({path:OUT+'home.png'}); console.log('home');
  await go('/catalog/driveshafts'); await p.screenshot({path:OUT+'complete.png'}); console.log('complete');
  await go('/shop', {scrollY:470}); await p.screenshot({path:OUT+'shop.png'}); console.log('shop');
  // designer: set dims via hash, scroll to the drawing
  await go('/driveshaft-designer/1/01+01R', {wait:1600});
  await p.evaluate(()=>{ location.hash='#C1=52.25&W1=48.5&T1=3.5'; }); await p.waitForTimeout(900);
  const draw = await p.$('.dsg__svg, #dsg-svg, .designer-draw, svg.dsg__canvas, svg');
  if (draw) await draw.scrollIntoViewIfNeeded().catch(()=>{});
  await p.evaluate(()=>scrollBy(0,-60)); await p.waitForTimeout(500);
  await p.screenshot({path:OUT+'designer.png'}); console.log('designer');
  // account: fresh, do NOT open assistant
  await go('/account', {wait:1700});
  await p.screenshot({path:OUT+'account.png'}); console.log('account');
  await b.close();
})().catch(e=>{console.error('FAIL', e.message); process.exit(1);});
