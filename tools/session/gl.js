const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:1440,height:1000}})).newPage();
 for(let i=0;i<12;i++){ await p.goto('https://dev.udshafts.com/return-request-form',{waitUntil:'domcontentloaded'}); if(await p.$('.rma2-glance .k b')) break; await p.waitForTimeout(6000);}
 const el=await p.$('.rma2-glance'); await el.scrollIntoViewIfNeeded(); const box=await el.boundingBox();
 await p.screenshot({path:'gl.png',clip:{x:box.x-30,y:box.y-60,width:box.width+60,height:box.height+90}}); await b.close(); })();
