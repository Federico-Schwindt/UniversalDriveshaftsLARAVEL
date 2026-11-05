const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  for (const [w,h,tag] of [[1280,860,'desk'],[390,844,'phone']]) {
    const ctx=await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:2});
    const p=await ctx.newPage();
    const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
    await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
    await p.fill('#l-email','assistant-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
    await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}), p.click('form.authform button[type=submit], form.authform .btn')]);
    // mock only the chat answer — everything else is the real page and the real widget
    await p.route('**/assistant', async r => {
      if (r.request().method()!=='POST') return r.continue();
      await new Promise(x=>setTimeout(x,700));
      r.fulfill({contentType:'application/json',body:JSON.stringify({text:"For a **1310** joint you'll want one of these. Both are greasable and in stock:\n- **5-1310X** — standard 1310 u-joint\n- **5-153X** — 1310 with half-round caps\nTell me your vehicle and I'll confirm which one fits.",cards:[{sku:"5-1310X",name:"Universal Joint 1310 series, greasable, 1.062 cap x 3.219 span",price_usd:24.95,stock:"in stock",url:"/parts/5-1310X",image:null},{sku:"5-153X",name:"Universal Joint 1310 half-round caps",price_usd:31.40,stock:"in stock",url:"/parts/5-153X",image:null},{sku:"5-1410X",name:"Universal Joint 1410 series",price_usd:39.10,stock:"out of stock",url:"/parts/5-1410X",image:null}],handoff:false,left_today:59})});
    });
    await p.goto('https://dev.udshafts.com/shop',{waitUntil:'networkidle'});
    const launcher=await p.$('.ua-launch'); console.log(tag,'launcher present:',!!launcher);
    await p.screenshot({path:`ua-${tag}-0.png`});
    await p.click('.ua-launch'); await p.waitForTimeout(700);
    await p.screenshot({path:`ua-${tag}-1.png`});
    await p.click('.ua-chip'); await p.waitForTimeout(1600);
    await p.screenshot({path:`ua-${tag}-2.png`});
    await p.click('[data-ua-person]'); await p.waitForTimeout(500);
    await p.screenshot({path:`ua-${tag}-3.png`});
    console.log(tag,'errors:',errs.length?errs:'none');
    await ctx.close();
  }
  await b.close();
})();
