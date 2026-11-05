const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/online-builder',{waitUntil:'networkidle'});
console.log(JSON.stringify(await p.evaluate(()=>{const w=document.querySelector('.bldwrap .wrap'),d=document.querySelector('#ubs-dsb'),h=document.querySelector('.bldhelp');const cs=e=>{const c=getComputedStyle(e);return {pad:c.paddingLeft+'/'+c.paddingRight,margin:c.marginLeft+'/'+c.marginRight,width:c.width,maxW:c.maxWidth}};return {wrap:cs(w),builder:cs(d),help:cs(h),wrapChildren:[...w.children].map(e=>e.tagName+'.'+(e.className||'').toString().split(' ')[0])}})));
await b.close();})();
