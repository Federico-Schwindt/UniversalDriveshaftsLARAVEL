const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [w,name] of [[1400,'flag-register'],[390,'flag-register-m']]){const p=await b.newPage({viewport:{width:w,height:900}});p.on('pageerror',e=>console.log('PAGEERROR',e.message));
await p.goto('https://dev.udshafts.com/register?x='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(400);
const el=await p.$('#r-phone'); await el.click(); await p.keyboard.type('8135246491');
await p.click('.tel__cc'); await p.waitForTimeout(250); await p.keyboard.type('mex'); await p.waitForTimeout(150);
const m=await p.evaluate(()=>{const wr=document.querySelector('.tel');const r=wr.getBoundingClientRect();const menu=wr.querySelector('.tel__menu').getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,menuH:menu.height,items:[...wr.querySelectorAll('.tel__list li')].map(l=>l.textContent.trim()),flagOk:!!wr.querySelector('.tel__flag').naturalWidth};});
console.log(name,JSON.stringify(m));
await p.screenshot({path:process.env.SP+'/shots/'+name+'-open.png',clip:{x:Math.max(0,m.x-30),y:Math.max(0,m.y-50),width:Math.min(w-Math.max(0,m.x-30),m.w+60),height:m.h+m.menuH+90}});
await p.keyboard.press('Enter'); await p.waitForTimeout(150);
console.log(name,'picked',await p.evaluate(()=>{const wr=document.querySelector('.tel');return wr.querySelector('.tel__code').textContent+' | '+wr.querySelector('input[type=tel]').value+' | '+wr.querySelector('input[type=hidden]').value;}));
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:Math.max(0,m.x-30),y:Math.max(0,m.y-50),width:Math.min(w-Math.max(0,m.x-30),m.w+60),height:m.h+90}});
await p.close();}
await b.close();})();
