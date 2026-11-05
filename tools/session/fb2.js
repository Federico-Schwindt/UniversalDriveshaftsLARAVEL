const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:1440,height:1000}})).newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'}); await p.fill('#l-email','freight-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
 await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit], form.authform .btn')]);
 for(let i=0;i<10;i++){ await p.goto('https://dev.udshafts.com/admin/orders/27',{waitUntil:'domcontentloaded'}); await p.click('[data-modal="freight"]'); await p.waitForTimeout(800); if(await p.$('.modal [data-fr-why]')) break; await p.waitForTimeout(5000); }
 const why=async()=>p.evaluate(()=>document.querySelector('.modal [data-fr-why]')?.textContent);
 console.log('1)', await why());
 await p.click('.modal button:has-text("Get freight prices")'); await p.waitForTimeout(4000);
 console.log('2)', await why()); await p.screenshot({path:'fb-why.png'});
 console.log('errors:', errs.length?errs:'none'); await b.close(); })();
