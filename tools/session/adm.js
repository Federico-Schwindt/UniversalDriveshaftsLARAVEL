const { chromium } = require(process.env.PWC);
const tag=process.argv[2]||'before';
const pages=[['dash','/admin'],['orders','/admin/orders'],['order','/admin/orders/10'],['neworder','/admin/orders/new'],['products','/admin/products'],['product','/admin/products/41808'],['inventory','/admin/inventory'],['customers','/admin/customers'],['leads','/admin/leads'],['lead','/admin/leads/2'],['coupons','/admin/coupons'],['designs','/admin/designs'],['rma','/admin/rma'],['drives','/admin/driveshafts'],['cats','/admin/categories'],['set-store','/admin/settings'],['set-ship','/admin/settings?tab=shipping'],['set-ai','/admin/settings?tab=assistant'],['set-int','/admin/settings?tab=integrations']];
(async()=>{
  const b=await chromium.launch({headless:true});
  for (const [w,h,dev] of [[1440,900,'d'],[390,844,'m']]) {
    const ctx=await b.newContext({viewport:{width:w,height:h}});
    const p=await ctx.newPage();
    const errs=[]; p.on('pageerror',e=>errs.push(e.message));
    await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
    await p.fill('#l-email','admin-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
    await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}), p.click('form.authform button[type=submit], form.authform .btn')]);
    for (const [n,u] of pages) {
      const r=await p.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle',timeout:45000}).catch(e=>null);
      const ov=await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
      await p.screenshot({path:`adm/${tag}-${dev}-${n}.png`,fullPage:dev==='d'?false:false});
      console.log(dev,n,r?r.status():'ERR', ov?'H-OVERFLOW':'');
    }
    if (dev==='d') { await p.goto('https://dev.udshafts.com/admin/orders/10',{waitUntil:'networkidle'}); const btn=await p.$('[data-modal="label"]'); if(btn){await btn.click(); await p.waitForTimeout(2500); await p.screenshot({path:`adm/${tag}-d-labelmodal.png`});} }
    console.log(dev,'errors',errs.slice(0,5));
    await ctx.close();
  }
  await b.close();
})();
