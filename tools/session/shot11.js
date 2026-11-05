const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:800}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50',{waitUntil:'networkidle'});await p.screenshot({path:`${process.env.SP}/dsg-step3b.png`,clip:{x:0,y:300,width:1440,height:500}});
const m=await b.newPage({viewport:{width:390,height:844},deviceScaleFactor:2});await m.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50',{waitUntil:'networkidle'});await m.screenshot({path:`${process.env.SP}/dsg-step3-phone.png`,clip:{x:0,y:380,width:390,height:420}});
console.log('ok');await b.close();})();
