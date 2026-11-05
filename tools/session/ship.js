const { chromium } = require(process.env.PWC);
const fs=require('fs');
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1280,height:1000}});
  await ctx.addCookies(JSON.parse(fs.readFileSync('cookies.json','utf8')));
  const p=await ctx.newPage();
  await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'domcontentloaded'});
  await p.waitForTimeout(4000);
  const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content);
  for (const [country,state,zip] of [['US','FL','33605'],['US','CA','90210'],['US','TX','00000'],['US','FL','']]) {
    const r=await p.evaluate(async([c,s,z,t])=>{const r=await fetch('/checkout/shipping-options',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({country:c,state:s,zip:z,postcode:z})});return r.status+' '+(await r.text()).slice(0,600)},[country,state,zip,tok]);
    console.log(country,state,zip,'=>',r);
  }
  await b.close();
})();
