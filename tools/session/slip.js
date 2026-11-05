const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:900,height:900}})).newPage();
 await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'}); await p.fill('#l-email','slip-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
 await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit], form.authform .btn')]);
 await p.addInitScript(()=>{window.print=()=>{}});
 for (const [u,f] of [['/admin/orders/27/packing-slip','slip-1.png'],['/admin/orders/print?ids=27','slip-2.png']]) {
   await p.goto('https://dev.udshafts.com'+u,{waitUntil:'load'}); await p.waitForTimeout(1500);
   const r=await p.evaluate(()=>({logo:(()=>{const i=document.querySelector('.logo');return i&&i.naturalWidth})(),imgs:[...document.querySelectorAll('img.ph')].map(i=>i.naturalWidth)}));
   console.log(u,JSON.stringify(r)); await p.screenshot({path:f}); }
 await b.close(); })();
