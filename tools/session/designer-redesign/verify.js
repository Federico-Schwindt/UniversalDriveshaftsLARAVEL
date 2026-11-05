const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2];
  const b = await chromium.launch({headless:true});
  const ctx = await b.newContext({viewport:{width:1440,height:900}, ignoreHTTPSErrors:true});
  const p = await ctx.newPage(); p.on('pageerror', e => console.log('PAGEERROR', e.message));
  const r = await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#C1=22.00&T1=3.00', {waitUntil:'networkidle'});
  console.log('status', r.status());
  await p.waitForSelector('#dsg.is-ready');
  await p.click('.dim-text[data-tab-index="1"]'); await p.keyboard.type('52.25'); await p.keyboard.press('Enter'); await p.keyboard.press('Escape');
  await p.waitForTimeout(300);
  console.log('drawing A1 =', await p.textContent('#dim-text-a1'));
  console.log('table A1 =', await p.textContent('#dsg-table tr[data-dim="A1"] .dsg__val'));
  console.log('hash =', await p.evaluate(() => location.hash));
  console.log('send =', await p.getAttribute('#dsg-send','href'));
  await p.screenshot({path:`${out}/desktop-result.png`, fullPage:true});
  await p.emulateMedia({media:'print'});
  await p.pdf({path:`${out}/print.pdf`, format:'Letter', landscape:true, printBackground:true});
  await p.emulateMedia({media:'screen'});
  await ctx.close();
  const m = await b.newContext({viewport:{width:390,height:844}, ignoreHTTPSErrors:true, deviceScaleFactor:2});
  const q = await m.newPage(); q.on('pageerror', e => console.log('PAGEERROR', e.message));
  for (const [i,u] of ['/driveshaft-designer','/driveshaft-designer/2/01+50','/driveshaft-designer/2/01+50+01R#A1=52.25&C1=22.00&T1=3.00'].entries()) { const rr = await q.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'}); console.log('phone', u, rr.status()); await q.waitForTimeout(400); await q.screenshot({path:`${out}/phone-${i}.png`, fullPage:true}); }
  await m.close();
  const d = await b.newContext({viewport:{width:1440,height:900}, ignoreHTTPSErrors:true});
  const dp = await d.newPage(); dp.on('pageerror', e => console.log('PAGEERROR', e.message));
  for (const [i,u] of ['/driveshaft-designer','/driveshaft-designer/2/01+50'].entries()) { const rr = await dp.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'}); console.log('desktop', u, rr.status()); await dp.waitForTimeout(400); await dp.screenshot({path:`${out}/desktop-${i}.png`, fullPage:true}); }
  await d.close(); await b.close();
})();
