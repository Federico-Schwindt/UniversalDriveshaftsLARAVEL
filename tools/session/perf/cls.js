const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,w] of [['/shop/driveshaft-components/u-joints',390],['/shop/driveshaft-components/flange-yokes',390]]){const p=await b.newPage({viewport:{width:w,height:900}});
await p.addInitScript(()=>{window.__shifts=[];try{new PerformanceObserver(l=>{for(const e of l.getEntries()){if(e.hadRecentInput)continue;window.__shifts.push({v:+e.value.toFixed(4),t:Math.round(e.startTime),nodes:(e.sources||[]).map(s=>{const n=s.node;if(!n)return '?';return (n.tagName||n.nodeName)+(n.id?'#'+n.id:'')+(n.className&&typeof n.className==='string'?'.'+n.className.split(' ').slice(0,2).join('.'):'')+(n.tagName==='IMG'?' '+(n.currentSrc||'').split('/').pop().slice(0,30):'');}).slice(0,3)});}}).observe({type:'layout-shift',buffered:true});}catch(e){}});
await p.goto('https://dev.udshafts.com'+path,{waitUntil:'networkidle'});await p.waitForTimeout(800);
console.log(path,w,JSON.stringify(await p.evaluate(()=>window.__shifts.sort((a,b)=>b.v-a.v).slice(0,5))));await p.close();}
await b.close();})();
