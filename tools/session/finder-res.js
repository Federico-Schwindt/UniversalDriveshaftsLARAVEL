const {chromium}=require(process.env.PWC);const S=process.argv[2];
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
const c=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2});const p=await c.newPage();
p.on('pageerror',e=>console.log('pageerror',e.message));
await p.goto('https://dev.udshafts.com/catalog/driveshafts?make=Ford&year=2015&model=F-150',{waitUntil:'load'});await p.waitForTimeout(2500);
console.log('scrollW',await p.evaluate(()=>[document.documentElement.scrollWidth,innerWidth]));
await p.screenshot({path:S+'/fdr-after-m-step4.png'});
const t=await p.$('.fdr-tile'); if(t){await t.click();await p.waitForTimeout(2500);}
await p.screenshot({path:S+'/fdr-after-m-results.png'});
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
