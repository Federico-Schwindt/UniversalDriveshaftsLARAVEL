const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,name,w,sel] of [['/register','ph-register',1400,'#r-phone'],['/contact','ph-contact',1400,'#c-tel'],['/register','ph-register-m',390,'#r-phone'],['/checkout','ph-checkout',1400,'input[type=tel]']]){
  const p=await b.newPage({viewport:{width:w,height:900}});await p.goto('https://dev.udshafts.com'+path+'?x='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(400);
  const el=await p.$(sel); if(!el){console.log(name,'no field (url now',p.url(),')');await p.close();continue;}
  await el.click(); await p.keyboard.type('8135246491'); await p.waitForTimeout(100);
  const m=await p.evaluate(s=>{const i=document.querySelector(s);const wr=i.closest('.tel');const r=wr.getBoundingClientRect();return {sel:wr.querySelector('select').getBoundingClientRect().width,inp:i.getBoundingClientRect().width,x:r.x,y:r.y,w:r.width,h:r.height};},sel);
  console.log(name,JSON.stringify(m));
  await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:Math.max(0,m.x-40),y:Math.max(0,m.y-50),width:Math.min(w-Math.max(0,m.x-40),m.w+80),height:m.h+90}});
  await p.close();}
await b.close();})();
