const { chromium } = require(process.env.PWC);
const OUT = process.env.HOME + '/pti-work/reel/shots/';
const HIDE = `.floaty,.ua-launch,[data-aitr],.aitr{display:none!important}`;
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport:{width:402,height:874}, deviceScaleFactor:2, isMobile:true, hasTouch:true });
  await ctx.addCookies([{ name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage();
  await p.goto('https://dev.udshafts.com/driveshaft-designer/1/01+01R#C1=52.25&W1=48.5&T1=3.5', { waitUntil:'networkidle' });
  await p.addStyleTag({content:HIDE}); await p.waitForTimeout(1500);
  const el = await p.$('.dsg__sheet'); await el.scrollIntoViewIfNeeded();
  await p.evaluate(() => { const r = document.querySelector('.dsg__sheet').getBoundingClientRect(); scrollBy(0, r.top - 120); });
  await p.waitForTimeout(600);
  await p.screenshot({ path: OUT + 'designer.png' });
  await el.screenshot({ path: OUT + 'designer-sheet.png' });
  console.log('designer ok'); await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
