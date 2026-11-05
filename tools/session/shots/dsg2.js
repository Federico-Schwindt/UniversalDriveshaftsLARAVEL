const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1400,height:1000}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/1/02Q+03R?x='+Date.now()+'#A1=52.25&C1=48.5&X2=1.50&Y2=2.25&Z2=4',{waitUntil:'networkidle'});await p.waitForTimeout(800);
const box=await (await p.$('#dsg-svg')).boundingBox(); await p.screenshot({path:process.env.SP+'/shots/dsg-02Q.png',clip:{x:box.x,y:box.y,width:box.width,height:Math.min(box.height,700)}});console.log('ok');await b.close();})();
