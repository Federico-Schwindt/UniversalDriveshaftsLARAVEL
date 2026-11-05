const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2];
  const b = await chromium.launch({headless:true});
  // printable area of landscape Letter with .4in margins at 96dpi: 10.2in x 7.7in
  const ctx = await b.newContext({viewport:{width:979,height:739}, ignoreHTTPSErrors:true, deviceScaleFactor:2});
  const p = await ctx.newPage(); p.on('pageerror', e => console.log('PAGEERROR', e.message));
  await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#A1=52.25&C1=22.00&T1=3.00', {waitUntil:'networkidle'});
  await p.waitForSelector('#dsg.is-ready');
  await p.emulateMedia({media:'print'});
  await p.waitForTimeout(300);
  console.log('placeholder B1 in print =', await p.textContent('#dim-text-b1'));
  const h = await p.evaluate(() => document.documentElement.scrollHeight); console.log('print doc height px', h, '(fits 739?)', h <= 739);
  await p.screenshot({path:`${out}/print-sheet.png`, fullPage:true});
  await p.pdf({path:`${out}/print.pdf`, format:'Letter', landscape:true, printBackground:true});
  await p.emulateMedia({media:'screen'}); await p.waitForTimeout(100);
  console.log('placeholder B1 back on screen =', await p.textContent('#dim-text-b1'));
  await b.close();
})();
