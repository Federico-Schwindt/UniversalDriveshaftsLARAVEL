const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,name] of [['/shop/complete-driveshafts','iph-group'],['/driveshafts','iph-hub'],['/shop/complete-driveshafts/by-vehicle','iph-byveh']]){const p=await b.newPage({viewport:{width:414,height:896},deviceScaleFactor:2,userAgent:'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'});
await p.goto('https://dev.udshafts.com'+path+'?x='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(300);
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:0,y:0,width:414,height:896}});await p.close();}
await b.close();console.log('ok');})();
