const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  for (const [n,o] of [['d',{viewport:{width:1440,height:900}}],['m',{viewport:{width:390,height:844},isMobile:true,deviceScaleFactor:2}]]) {
    const c = await b.newContext({ ...o, ignoreHTTPSErrors:true }); const p = await c.newPage();
    await p.goto('https://dev.udshafts.com'+process.env.U, { waitUntil:'networkidle' });
    await p.screenshot({ path: process.env.S+'/pay-'+n+'.png', fullPage:n==='m' });
    console.log(n, await p.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth+1));
    await c.close();
  }
  await b.close();
})();
