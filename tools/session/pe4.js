const { chromium } = require(process.env.PWC);
const fs=require('fs');
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1280,height:1000}});
  await ctx.addCookies(JSON.parse(fs.readFileSync('cookies.json','utf8')));
  const p=await ctx.newPage();
  await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'domcontentloaded'});
  await p.waitForTimeout(7000);
  console.log('select present:', await p.evaluate(()=>!!document.getElementById('pay-method')), 'hidden:', await p.evaluate(()=>document.querySelector('[name=payment_method]').type));
  for (const c of [3000, 5000, 8192]) {
    await p.evaluate(c=>window.udsStripeAmount(c), c);
    await p.waitForTimeout(2500);
    const f=p.frames().find(f=>/elements-inner-payment/.test(f.url()));
    const t=await f.evaluate(()=>document.body.innerText.split('\n').slice(0,3).join('|'));
    console.log(c, t);
  }
  await b.close();
})();
