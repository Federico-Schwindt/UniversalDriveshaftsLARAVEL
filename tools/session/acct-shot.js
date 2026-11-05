const { chromium } = require(process.env.PWC);
(async()=>{ const [,, prefix, ...paths]=process.argv; const b=await chromium.launch({headless:true});
 for (const [vw,tag] of [[1366,'d'],[390,'m']]) { const ctx=await b.newContext({viewport:{width:vw,height:900}}); const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'}); await p.fill('#l-email','acct-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
  await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit], form.authform .btn')]);
  for (const u of paths) { await p.goto('https://dev.udshafts.com'+u,{waitUntil:'domcontentloaded'}); await p.waitForTimeout(700);
   const ov=await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1); const f=`${prefix}-${tag}-${u.replace(/\W+/g,'_')}.png`; await p.screenshot({path:f,fullPage:true}); console.log(f, ov?'OVERFLOW':'', errs.join('|')); }
  await ctx.close(); } await b.close(); })();
