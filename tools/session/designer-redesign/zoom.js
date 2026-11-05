const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2];
  const b = await chromium.launch({headless:true});
  const ctx = await b.newContext({viewport:{width:979,height:739}, ignoreHTTPSErrors:true, deviceScaleFactor:4});
  const p = await ctx.newPage();
  await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#A1=52.25', {waitUntil:'networkidle'});
  await p.waitForSelector('#dsg.is-ready');
  await p.emulateMedia({media:'print'}); await p.waitForTimeout(300);
  await p.screenshot({path:`${out}/zoom-head.png`, clip:{x:0,y:0,width:300,height:40}});
  console.log(await p.evaluate(() => { const e=document.querySelector('.dsg__sheet-no'); const cs=getComputedStyle(e); return [cs.textDecorationLine, cs.fontFamily, cs.fontWeight].join(' | '); }));
  await b.close();
})();
