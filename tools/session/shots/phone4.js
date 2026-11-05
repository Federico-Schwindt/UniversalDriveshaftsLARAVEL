const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1400,height:900}});
// put a real part in the cart, then open checkout and exercise the phone field + country sync
await p.goto('https://dev.udshafts.com/parts/5-1310XU',{waitUntil:'networkidle'});
const csrf=await p.evaluate(()=>(document.querySelector('meta[name="csrf-token"]')||{}).content||'');
const r=await p.evaluate(async(c)=>{const f=await fetch('/quick-order/add',{method:'POST',headers:{'X-CSRF-TOKEN':c,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({lines:JSON.stringify([{sku:'5-1310XU',qty:1}])})});return f.status+' '+(await f.text()).slice(0,80);},csrf);
console.log('add', r);
await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'networkidle'});await p.waitForTimeout(500);console.log('url',p.url());
const el=await p.$('input[type=tel]'); if(!el){console.log('no tel');await b.close();return;}
const m=await p.evaluate(()=>{const i=document.querySelector('input[type=tel]');const wr=i.closest('.tel');const r=wr.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,sel:wr.querySelector('select').value};});console.log('checkout tel',JSON.stringify(m));
await p.selectOption('#shipping_country','MX').catch(()=>{}); await p.waitForTimeout(200);
console.log('after shipping_country=MX, cc =',await p.evaluate(()=>document.querySelector('.tel select').value));
await el.click(); await p.keyboard.type('5512345678'); console.log('hidden',await p.evaluate(()=>document.querySelector('.tel input[type=hidden]').value));
await p.screenshot({path:process.env.SP+'/shots/ph-checkout.png',clip:{x:Math.max(0,m.x-40),y:Math.max(0,m.y-50),width:Math.min(1400,m.w+80),height:m.h+90}});
await b.close();})();
