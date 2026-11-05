const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1280,height:900},deviceScaleFactor:2});
await p.goto('file://'+process.env.SP+'/style/demo.html',{waitUntil:'networkidle'});await p.waitForTimeout(1200);
const sheets=await p.$$('.sheet');const names=['1-twopiece-preview','2-onepiece-result','3-twopiece-completed'];
for(let i=0;i<sheets.length;i++){await sheets[i].screenshot({path:`${process.env.SP}/style/${names[i]}.png`});}
console.log('rendered',sheets.length,'sheets');await b.close();})();
