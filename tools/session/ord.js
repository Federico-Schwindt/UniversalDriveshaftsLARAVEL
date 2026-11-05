const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  for (const [w,h,tag] of [[1280,900,'d'],[390,844,'m']]) {
    const ctx=await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:1});
    const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
    await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
    await p.fill('#l-email','orders-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
    await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}), p.click('form.authform button[type=submit]')]);
    await p.goto('https://dev.udshafts.com/account/orders',{waitUntil:'networkidle'});
    await p.screenshot({path:`ord/${tag}-account.png`,fullPage:true});
    const href=await p.$eval('a.eye',a=>a.getAttribute('href')).catch(()=>null); console.log(tag,'account link →',href);
    await p.click('a.eye'); await p.waitForLoadState('networkidle'); console.log(tag,'landed on',p.url().replace('https://dev.udshafts.com',''));
    await p.screenshot({path:`ord/${tag}-status.png`,fullPage:true});
    const old=await p.goto('https://dev.udshafts.com/account/orders/UDS-QA-ORD1',{waitUntil:'domcontentloaded'}); console.log(tag,'old URL →',p.url().replace('https://dev.udshafts.com',''));
    await p.goto('https://dev.udshafts.com/return-request-form?order=UDS-QA-ORD1&e=orders-qa%40udshafts.test',{waitUntil:'networkidle'});
    await p.screenshot({path:`ord/${tag}-rma.png`,fullPage:true});
    // simulate the submit composition without posting
    await p.check('#rma-items li:first-child input[type=checkbox]').catch(()=>{});
    await p.click('.rma2-chip:nth-child(3) span'); console.log(tag,'installed warning shown:', await p.isVisible('#rma-installed'));
    await p.goto('https://dev.udshafts.com/track-order',{waitUntil:'networkidle'}); await p.screenshot({path:`ord/${tag}-track.png`,fullPage:false});
    console.log(tag,'errors:',errs.length?errs:'none');
    await ctx.close();
  }
  await b.close();
})();
