const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,name,w] of [['/parts/5-1310-1X','alt-desktop',1400],['/parts/3133-311','about-desktop',1400],['/parts/5-1310-1X','alt-mobile',390]]){const p=await b.newPage({viewport:{width:w,height:900}});await p.goto('https://dev.udshafts.com'+path+'?x='+Date.now(),{waitUntil:'networkidle'});
const sel=name.startsWith('about')?'#pdp-desc-h':'#pdp-alts'; const el=await p.$(sel); if(!el){console.log(name,'missing',sel);await p.close();continue;} await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(300); const box=await el.boundingBox();
await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',clip:{x:0,y:Math.max(0,box.y-40),width:w,height:Math.min(620,900)}}); await p.close();}
await b.close();console.log('ok');})();
