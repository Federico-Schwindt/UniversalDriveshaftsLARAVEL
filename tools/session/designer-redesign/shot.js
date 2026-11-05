const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2]; const urls = ['/driveshaft-designer','/driveshaft-designer/2/01+50','/driveshaft-designer/2/01+50+01R#A1=52.25&C1=22.00&T1=3.00'];
  const b = await chromium.launch({headless:true});
  for (const w of [1440, 390]) {
    const ctx = await b.newContext({viewport:{width:w,height:w>1000?900:844}, ignoreHTTPSErrors:true, deviceScaleFactor:1});
    const p = await ctx.newPage(); p.on('pageerror', e => console.log('PAGEERROR', e.message));
    for (let i=0;i<urls.length;i++){ const r = await p.goto('https://dev.udshafts.com'+urls[i], {waitUntil:'networkidle'}); console.log(w, urls[i], r.status()); await p.waitForTimeout(400); await p.screenshot({path:`${out}/${w}-${i}.png`, fullPage:true}); }
    await ctx.close();
  }
  await b.close();
})();
