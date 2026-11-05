const {chromium}=require(process.env.PWC);const S=process.argv[2],tag=process.argv[3];
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
for(const [w,h,n] of [[1440,900,'d'],[390,844,'m']]){const c=await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:n==='m'?2:1});const p=await c.newPage();
 if(tag==='ref'){await p.goto('https://dev.udshafts.com/driveshaft-designer',{waitUntil:'load'});await p.waitForTimeout(2500);await p.screenshot({path:`${S}/ref-designer-${n}.png`});}
 else{await p.goto('https://dev.udshafts.com/catalog/driveshafts',{waitUntil:'load'});await p.waitForTimeout(1500);await p.screenshot({path:`${S}/fdr-${tag}-${n}.png`});
  const t=await p.$('.fdr-tile');if(t){await t.click();await p.waitForTimeout(2000);await p.screenshot({path:`${S}/fdr-${tag}-${n}-step2.png`});}}
 await c.close();}
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
