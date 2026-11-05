const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50',{waitUntil:'networkidle'});await p.screenshot({path:`${process.env.SP}/dsg-live-preview.png`,clip:{x:0,y:300,width:1440,height:900}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#A1=52.25&C1=22.00&T1=3.00',{waitUntil:'networkidle'});await p.waitForSelector('#dsg.is-ready');await p.waitForTimeout(300);
const r=await p.$('#dsg-result');await r.screenshot({path:`${process.env.SP}/dsg-live-result.png`});console.log('ok');await b.close();})();
