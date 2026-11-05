const {chromium}=require(process.env.PWC);const S=process.argv[2];
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
let c=await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:1.5});let p=await c.newPage();
await p.goto('https://dev.udshafts.com/',{waitUntil:'load'});await p.waitForTimeout(2500);await p.screenshot({path:S+'/social-assets/site-desktop.jpg',type:'jpeg',quality:88});
await p.goto('https://dev.udshafts.com/catalog/driveshafts',{waitUntil:'load'});await p.waitForTimeout(2000);await p.screenshot({path:S+'/social-assets/site-finder.jpg',type:'jpeg',quality:88});
await c.close();c=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:3,isMobile:true,hasTouch:true});p=await c.newPage();
await p.goto('https://dev.udshafts.com/',{waitUntil:'load'});await p.waitForTimeout(2500);await p.screenshot({path:S+'/social-assets/site-mobile.jpg',type:'jpeg',quality:88});
await p.goto('https://dev.udshafts.com/parts/5-1310XU',{waitUntil:'load'});await p.waitForTimeout(2000);await p.screenshot({path:S+'/social-assets/site-mobile-pdp.jpg',type:'jpeg',quality:88});
await b.close();})();
