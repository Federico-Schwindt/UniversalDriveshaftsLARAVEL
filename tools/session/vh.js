const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true});
 for (const [w,h,t] of [[1440,900,'d'],[390,844,'m']]) { const p=await (await b.newContext({viewport:{width:w,height:h}})).newPage(); const e=[]; p.on('pageerror',x=>e.push(x.message));
  for(let i=0;i<12;i++){ await p.goto('https://dev.udshafts.com/driveshafts/chrysler?lang=en',{waitUntil:'domcontentloaded'}); if(await p.$('.vhero')) break; await p.waitForTimeout(6000);}
  await p.screenshot({path:`vh-${t}.png`,fullPage:t==='m'}); const raw=await p.evaluate(()=>/@if|@endif/i.test(document.body.innerText)); console.log(t,'raw blade:',raw,'errors:',e.length); }
 await b.close(); })();
