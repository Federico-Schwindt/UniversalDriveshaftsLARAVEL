const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,name,h] of [['/driveshafts/honda/pilot','seo-vehicle',1500],['/series/1310','seo-series',1400],['/parts/5-1310XU','seo-product',1700]]){const p=await b.newPage({viewport:{width:1400,height:900}});await p.goto('https://dev.udshafts.com'+path,{waitUntil:'networkidle'});await p.waitForTimeout(400);await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:0,y:0,width:1400,height:h}});await p.close();}
await b.close();console.log('ok');})();
