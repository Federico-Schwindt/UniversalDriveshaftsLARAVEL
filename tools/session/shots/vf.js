const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [w,name] of [[1400,'vfilter'],[390,'vfilter-m']]){const p=await b.newPage({viewport:{width:w,height:900}});p.on('pageerror',e=>console.log('PAGEERROR',e.message));
await p.goto('https://dev.udshafts.com/shop/complete-driveshafts/by-vehicle?x='+Date.now(),{waitUntil:'networkidle'});
await p.selectOption('[data-v=make]','Honda'); await p.waitForURL(/make=Honda/); await p.waitForLoadState('networkidle');
await p.selectOption('[data-v=year]','2010'); await p.waitForURL(/year=2010/); await p.waitForLoadState('networkidle');
const models=await p.evaluate(()=>[...document.querySelectorAll('[data-v=model] option')].map(o=>o.value).filter(Boolean));
await p.selectOption('[data-v=model]','Pilot'); await p.waitForURL(/model=Pilot/); await p.waitForLoadState('networkidle');
console.log(name, p.url().split('?')[1], 'models:', models.join(','), '|', await p.evaluate(()=>document.querySelector('.shop-count').textContent.trim()));
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:0,y:0,width:w,height:w>600?1000:1500}});await p.close();}
await b.close();})();
