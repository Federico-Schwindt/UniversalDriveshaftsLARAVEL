const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();
 await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R?lang=es',{waitUntil:'domcontentloaded'}); await p.waitForTimeout(3500);
 console.log((await p.evaluate(()=>document.querySelector('.dsg__body').innerText+' | '+document.querySelector('.dsg__title').innerText+' | '+document.querySelector('.dsg__after').innerText)).replace(/\s+/g,' ').slice(0,1500));
 await b.close(); })();
