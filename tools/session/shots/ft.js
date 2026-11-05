const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [w,h,name] of [[1400,900,'ft-desktop'],[390,800,'ft-mobile']]){const p=await b.newPage({viewport:{width:w,height:h}});p.on('pageerror',e=>console.log('PAGEERROR',e.message));
await p.goto('https://dev.udshafts.com/parts/TU3565?x='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(900);
console.log(name,'popup visible:',await p.evaluate(()=>!document.getElementById('ftpop').hidden));
await p.click('[data-ft="up"]');await p.click('[data-ft="up"]');await p.click('[data-ft="up"]');await p.waitForTimeout(150);
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png'});
await p.click('[data-ft-apply]');await p.waitForTimeout(200);
console.log(name,'qty after apply:',await p.evaluate(()=>document.getElementById('pdp-qty').value+' | '+document.querySelector('[data-ft-live]').textContent));
const box=await (await p.$('.pdp__pricerow')).boundingBox();await p.screenshot({path:process.env.SP+'/shots/'+name+'-after.png',clip:{x:Math.max(0,box.x-20),y:Math.max(0,box.y-20),width:Math.min(w,620),height:300}});
await p.close();}
await b.close();})();
