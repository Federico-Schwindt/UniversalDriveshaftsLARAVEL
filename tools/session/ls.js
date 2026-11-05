const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:2})).newPage();
 for(let i=0;i<12;i++){ await p.goto('https://dev.udshafts.com/?lang=es',{waitUntil:'domcontentloaded'}); if(await p.$('.langsw__flag')) break; await p.waitForTimeout(6000);}
 const el=await p.$('.langsw'); const bx=await el.boundingBox();
 await p.screenshot({path:'ls.png',clip:{x:bx.x-260,y:bx.y-8,width:bx.width+280,height:bx.height+16}}); await b.close(); })();
