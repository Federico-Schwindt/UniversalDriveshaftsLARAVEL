const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
for(const [n,u] of [['offroad','/custom-off-road-driveshafts'],['services','/services'],['about','/about-us'],['contact','/contact'],['shop','/shop'],['product','/parts/027SG80002']]){await p.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'});await p.screenshot({path:`${process.env.SP}/pg-${n}.png`,clip:{x:0,y:0,width:1440,height:1100},fullPage:true});}
console.log('page shots ok');await b.close();})();
