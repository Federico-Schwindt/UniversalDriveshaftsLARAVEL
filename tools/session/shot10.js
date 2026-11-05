const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:700}});
for(const [n,u] of [['step2','/driveshaft-designer/2/01'],['step3','/driveshaft-designer/2/01+50']]){await p.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'});await p.screenshot({path:`${process.env.SP}/dsg-${n}.png`,clip:{x:0,y:120,width:1440,height:560}});}
console.log('ok');await b.close();})();
