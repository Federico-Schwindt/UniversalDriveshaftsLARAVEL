const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1480,height:980},deviceScaleFactor:2});
await p.goto('file://'+process.env.SP+'/svg/ujoint-1310.svg');await p.waitForTimeout(400);await p.screenshot({path:process.env.SP+'/svg/ujoint-1310.png'});console.log('rendered');await b.close();})();
