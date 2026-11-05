const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/2/01+50+01R#A1=52.25&C1=22.00&T1=3.00',{waitUntil:'networkidle'});await p.waitForSelector('#dsg.is-ready');
const href=await p.$eval('#dsg-send',a=>a.getAttribute('href'));console.log('send href:',href);
await p.click('#dsg-send');await p.waitForLoadState('networkidle');console.log('landed:',p.url().slice(0,90));
await p.evaluate(()=>document.querySelector('#dsg-attach').scrollIntoView({block:'start'}));await p.waitForTimeout(400);
await p.screenshot({path:`${process.env.SP}/contact-attach.png`,clip:{x:0,y:0,width:1440,height:900}});
console.log('message prefill:',JSON.stringify(await p.$eval('#c-msg',t=>t.value.slice(0,120))));await b.close();})();
