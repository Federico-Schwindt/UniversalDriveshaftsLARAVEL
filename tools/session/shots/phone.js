const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const B='https://dev.udshafts.com';
for (const [path,name,w,sel] of [['/register','ph-register',1400,'#r-phone'],['/contact','ph-contact',1400,'#c-tel'],['/rma','ph-rma',1400,'input[type=tel]'],['/register','ph-register-m',390,'#r-phone']]){
  const p=await b.newPage({viewport:{width:w,height:900}});await p.goto(B+path,{waitUntil:'networkidle'});await p.waitForTimeout(400);
  const el=await p.$(sel); if(!el){console.log(name,'no field');await p.close();continue;}
  await el.click(); await p.keyboard.type('8135246491'); await p.waitForTimeout(150);
  const info=await p.evaluate(s=>{const i=document.querySelector(s);const w=i.closest('.tel');return {wrapped:!!w,shown:i.value,cc:w&&w.querySelector('select').value,hidden:w&&w.querySelector('input[type=hidden]').value,hiddenName:w&&w.querySelector('input[type=hidden]').name,valid:i.checkValidity()};},sel);
  console.log(name,JSON.stringify(info));
  const box=await el.boundingBox(); await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:Math.max(0,box.x-260),y:Math.max(0,box.y-70),width:Math.min(w,700),height:170}});
  // international paste + country switch
  await el.fill(''); await p.keyboard.type('+52 55 1234 5678'); await p.waitForTimeout(100);
  console.log(name,'intl',JSON.stringify(await p.evaluate(s=>{const i=document.querySelector(s);const w=i.closest('.tel');return {shown:i.value,cc:w.querySelector('select').value,hidden:w.querySelector('input[type=hidden]').value};},sel)));
  await p.close();}
await b.close();})();
