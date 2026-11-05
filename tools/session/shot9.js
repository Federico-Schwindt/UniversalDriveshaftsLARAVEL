const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const ctx=await b.newContext({viewport:{width:1440,height:900}});const p=await ctx.newPage();
await p.goto('https://dev.udshafts.com/cart',{waitUntil:'networkidle'});
const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]').content);
await p.evaluate(async t=>{await fetch('/shop/catalog/add',{method:'POST',headers:{'X-CSRF-TOKEN':t,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:'id=11377&qty=2'});},tok);
for(const [n,u] of [['cart','/cart'],['checkout','/checkout'],['category','/shop/driveshaft-components/u-joints'],['finder','/catalog/driveshafts']]){await p.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'});await p.waitForTimeout(800);await p.screenshot({path:`${process.env.SP}/pg-${n}.png`,clip:{x:0,y:0,width:1440,height:1250},fullPage:true});}
console.log('cart/checkout/category/finder shots ok');await b.close();})();
