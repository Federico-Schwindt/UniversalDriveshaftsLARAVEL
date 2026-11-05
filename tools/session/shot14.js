const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/track-order',{waitUntil:'networkidle'});await p.screenshot({path:`${process.env.SP}/pg-track.png`,fullPage:true});
const m=await b.newPage({viewport:{width:390,height:844},deviceScaleFactor:2});await m.goto('https://dev.udshafts.com/track-order',{waitUntil:'networkidle'});await m.screenshot({path:`${process.env.SP}/pg-track-phone.png`,clip:{x:0,y:0,width:390,height:1100},fullPage:true});
console.log('ok');await b.close();})();
