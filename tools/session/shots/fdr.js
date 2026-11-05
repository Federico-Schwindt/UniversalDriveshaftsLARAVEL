const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:414,height:896},deviceScaleFactor:2});p.on('pageerror',e=>console.log('PAGEERROR',e.message));
await p.goto('https://dev.udshafts.com/catalog/driveshafts?x='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(400);
await p.screenshot({path:process.env.SP+'/shots/iph-finder.png',clip:{x:0,y:0,width:414,height:896}});
await p.click('text=ACURA').catch(()=>{}); await p.waitForTimeout(1200); console.log('after make click url:', p.url(), '| quick selects:', await p.evaluate(()=>document.querySelectorAll('#fdr-quick select').length));
await b.close();})();
