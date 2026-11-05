const { chromium } = require(process.env.PWC);
const fs=require('fs');
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1280,height:1000}});
  await ctx.addCookies(JSON.parse(fs.readFileSync('cookies.json','utf8')));
  const p=await ctx.newPage();
  await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'domcontentloaded'});
  await p.waitForTimeout(8000);
  console.log(await p.evaluate(()=>({total:window.UDS_TOTAL_CENTS, sel:!!document.getElementById('pay-method')})));
  const f=p.frames().find(f=>/elements-inner-payment/.test(f.url()));
  if(f){console.log((await f.evaluate(()=>document.body.innerText)).slice(0,300));}
  const el=await p.$('#stripe-card'); if(el) await el.screenshot({path:'pe3.png'});
  await b.close();
})();
