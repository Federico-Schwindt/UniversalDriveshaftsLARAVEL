const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
const edges=async(u,sel)=>{await p.goto('https://dev.udshafts.com'+u,{waitUntil:'networkidle'});return await p.evaluate(s=>{const e=document.querySelector(s);const r=e.getBoundingClientRect();const cs=getComputedStyle(e);return `${Math.round(r.left+parseFloat(cs.paddingLeft))}..${Math.round(r.right-parseFloat(cs.paddingRight))}`},sel)};
console.log('home content edges  :',await edges('/','.n-head'),' / hero text:',await edges('/','.n-hero__in'));
console.log('shop content edges  :',await edges('/shop','main .wrap'));
console.log('builder content     :',await edges('/online-builder','#ubs-dsb'));
await b.close();})();
