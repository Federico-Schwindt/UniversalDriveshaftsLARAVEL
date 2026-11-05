const {chromium}=require(process.env.PWC);
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage();
await p.goto('https://dev.udshafts.com/catalog/driveshafts?make=Ford',{waitUntil:'load'});await p.waitForTimeout(1500);
console.log(await p.evaluate(()=>{const o=document.querySelector('.fdr-steps'),w=document.getElementById('fdr'),bar=document.getElementById('fdr-bar');const cs=getComputedStyle(o);
return {ol:[o.getBoundingClientRect().left,cs.marginLeft,cs.paddingLeft],bar:[bar.getBoundingClientRect().left,getComputedStyle(bar).marginLeft,bar.className],wrap:[w.getBoundingClientRect().left,getComputedStyle(w).paddingLeft]};}));
await b.close();})();
