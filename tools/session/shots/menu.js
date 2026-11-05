const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,w,name] of [['/contact',1400,'menu-contact'],['/register',390,'menu-register-m']]){const p=await b.newPage({viewport:{width:w,height:900}});
await p.goto('https://dev.udshafts.com'+path+'?x='+Date.now(),{waitUntil:'networkidle'});
const closed=await p.evaluate(()=>getComputedStyle(document.querySelector('.tel__menu')).display);
await p.click('.tel__cc'); await p.waitForTimeout(300);
const m=await p.evaluate(()=>{const wr=document.querySelector('.tel');const r=wr.getBoundingClientRect();const mn=wr.querySelector('.tel__menu');const mr=mn.getBoundingClientRect();return {closed:null,tel:[r.x,r.y,r.width,r.height],menu:[mr.x,mr.y,mr.width,mr.height],offsetParent:mn.offsetParent&&mn.offsetParent.className};});
m.closed=closed; console.log(name,JSON.stringify(m));
const y=Math.max(0,m.tel[1]-40); await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:Math.max(0,m.tel[0]-20),y,width:Math.min(w,m.tel[2]+60),height:Math.min(900-y,m.tel[3]+m.menu[3]+70)}});
await p.close();}
await b.close();})();
