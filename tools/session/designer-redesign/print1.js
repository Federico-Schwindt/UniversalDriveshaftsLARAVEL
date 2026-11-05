const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2];
  const b = await chromium.launch({headless:true});
  const ctx = await b.newContext({viewport:{width:979,height:739}, ignoreHTTPSErrors:true, deviceScaleFactor:2});
  const p = await ctx.newPage(); p.on('pageerror', e => console.log('PAGEERROR', e.message));
  await p.goto('https://dev.udshafts.com/driveshaft-designer/1/01+01R', {waitUntil:'networkidle'});
  await p.waitForSelector('#dsg.is-ready');
  await p.emulateMedia({media:'print'}); await p.waitForTimeout(300);
  const h = await p.evaluate(() => document.documentElement.scrollHeight); console.log('1pc print height', h);
  await p.screenshot({path:`${out}/print-sheet-1pc.png`, fullPage:true});
  await p.pdf({path:`${out}/print-1pc.pdf`, format:'Letter', landscape:true, printBackground:true});
  await p.emulateMedia({media:'screen'});
  await p.setViewportSize({width:1440,height:900}); await p.waitForTimeout(200);
  await p.screenshot({path:`${out}/desktop-1pc.png`, fullPage:false});
  await b.close();
})();
