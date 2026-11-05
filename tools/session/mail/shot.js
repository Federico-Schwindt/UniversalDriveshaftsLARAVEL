const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:680,height:900}})).newPage();
 for (const [f,n] of [['01','es-confirmation'],['03','es-shipped'],['05','es-paylink']]) {
   await p.goto('file://'+process.cwd()+'/mail/es/'+f+'.html',{waitUntil:'load'}); await p.waitForTimeout(400);
   await p.screenshot({path:'mail/'+n+'.png',fullPage:true});
 } await b.close(); })();
