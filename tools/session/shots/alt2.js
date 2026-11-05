const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [w,name] of [[1400,'alt-desktop'],[390,'alt-mobile']]){const p=await b.newPage({viewport:{width:w,height:900}});p.on('pageerror',e=>console.log('PAGEERROR',e.message));await p.goto('https://dev.udshafts.com/parts/5-1310-1X?x='+Date.now(),{waitUntil:'networkidle'});
const el=await p.$('#pdp-alts'); await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(300); const box=await el.boundingBox();
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:0,y:Math.max(0,box.y-30),width:w,height:Math.min(w>600?560:800,900)}});
if (w>600){ await p.click('#pdp-alts .p-plus'); await p.waitForTimeout(900); console.log('cart count after row add:', await p.evaluate(()=>document.querySelector('[data-cart-count]').textContent)); }
await p.close();}
await b.close();})();
