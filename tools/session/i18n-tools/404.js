const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();
 const bad=[]; p.on('response',r=>{ if(r.status()>=400) bad.push(r.status()+' '+r.url()); });
 for (const u of ['/driveshaft-designer','/driveshaft-designer/2','/driveshaft-designer/2/01','/driveshaft-designer/2/01+50','/driveshaft-designer/2/01+50+01R']) for (const l of ['en','es']) { await p.goto('https://dev.udshafts.com'+u+'?lang='+l,{waitUntil:'networkidle'}).catch(()=>{}); }
 console.log(bad.length?bad.join('\n'):'no 4xx'); await b.close(); })();
