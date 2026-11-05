const { chromium } = require(process.env.PWC);
(async () => {
  const out = process.argv[2];
  const b = await chromium.launch({headless:true});
  const ctx = await b.newContext({viewport:{width:979,height:739}, ignoreHTTPSErrors:true, deviceScaleFactor:4});
  const p = await ctx.newPage();
  await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#A1=52.25', {waitUntil:'networkidle'});
  await p.waitForSelector('#dsg.is-ready');
  await p.emulateMedia({media:'print'}); await p.waitForTimeout(300);
  console.log(await p.evaluate(() => { let e=document.querySelector('.dsg__sheet-no'), out=[]; while(e){ const cs=getComputedStyle(e); out.push(e.tagName+'.'+e.className+' td='+cs.textDecorationLine+' fs='+cs.fontSize); e=e.parentElement;} return out.join('\n'); }));
  await p.evaluate(() => { document.querySelector('.dsg__sheet-no').style.fontSize='40px'; });
  await p.screenshot({path:`${out}/zoom-head2.png`, clip:{x:0,y:0,width:400,height:80}});
  await b.close();
})();
