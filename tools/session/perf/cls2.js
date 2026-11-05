const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:390,height:844}});
await p.addInitScript(()=>{window.__log=[];const t0=performance.now();const tick=()=>{const q=s=>{const e=document.querySelector(s);return e?Math.round(e.getBoundingClientRect().height):null};window.__log.push([Math.round(performance.now()-t0),q('.ucathead'),q('.usiblings'),q('.shop-controls'),q('.ptbl-wrap'),document.fonts?document.fonts.status:'?']);if(performance.now()-t0<2500)setTimeout(tick,100);};setTimeout(tick,0);});
await p.goto('https://dev.udshafts.com/shop/driveshaft-components/u-joints',{waitUntil:'networkidle'});await p.waitForTimeout(2600);
const log=await p.evaluate(()=>window.__log);let prev=null;for(const r of log){if(JSON.stringify(r.slice(1))!==JSON.stringify(prev)){console.log(r.join('\t'));prev=r.slice(1);}}
await b.close();})();
