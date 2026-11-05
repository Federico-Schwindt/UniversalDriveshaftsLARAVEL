const { chromium } = require(process.env.PWC);
(async()=>{const b=await chromium.launch({headless:true});const p=await (await b.newContext({viewport:{width:1440,height:900},ignoreHTTPSErrors:true})).newPage();
await p.goto('https://dev.udshafts.com'+process.env.U,{waitUntil:'networkidle'});
console.log(await p.evaluate(()=>['.payp','.payp__main','.payp__side','.payp__box','.kicker'].map(s=>{const e=document.querySelector(s),r=e.getBoundingClientRect(),c=getComputedStyle(e);return s+' top='+r.top+' mt='+c.marginTop+' pos='+c.position}).join('\n')));await b.close()})();
