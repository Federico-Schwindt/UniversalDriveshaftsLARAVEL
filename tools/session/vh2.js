const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true});
 for (const [u,t,w,h] of [['/driveshafts/chrysler','make-d',1440,900],['/driveshafts/chrysler','make-m',390,844],['/driveshafts/chrysler/300','model-d',1440,900]]) {
  const p=await (await b.newContext({viewport:{width:w,height:h}})).newPage(); const e=[]; p.on('pageerror',x=>e.push(x.message));
  await p.goto('https://dev.udshafts.com'+u+'?lang=es',{waitUntil:'domcontentloaded'}); await p.waitForTimeout(1500);
  const txt=await p.evaluate(()=>document.querySelector('main')?.innerText||document.body.innerText);
  const raw=/@if|@endif|:make|:model|:n\b|\{1\}/.test(txt);
  const over=await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
  await p.screenshot({path:`es-${t}.png`,fullPage:t==='make-m'}); console.log(t,'raw tokens:',raw,'overflow:',over,'errors:',e.length); }
 await b.close(); })();
