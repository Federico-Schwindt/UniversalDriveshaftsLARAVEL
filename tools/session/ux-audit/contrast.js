const {chromium}=require(process.env.PWC);
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1440,height:900}});
for(const u of ['/','/parts/5-1310XU','/shop','/catalog/driveshafts','/login','/online-builder']){await p.goto('https://dev.udshafts.com'+u,{waitUntil:'load'});await p.waitForTimeout(1500);
const r=await p.evaluate(()=>{const lum=c=>{const m=c.match(/[\d.]+/g);if(!m)return null;const [r,g,b]=m.slice(0,3).map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)});return {l:.2126*r+.7152*g+.0722*b,a:m[3]===undefined?1:+m[3]}};
const bg=e=>{while(e){const s=getComputedStyle(e);if(s.backgroundImage!='none')return null;const c=lum(s.backgroundColor);if(c&&c.a>0.9)return c.l;e=e.parentElement}return 1};
const out=new Map();const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let n;while(n=w.nextNode()){const t=n.textContent.trim();if(t.length<3)continue;const e=n.parentElement;if(!e.offsetParent)continue;const s=getComputedStyle(e);if(s.opacity<0.5)continue;const f=lum(s.color);const bl=bg(e);if(!f||bl==null)continue;const L1=Math.max(f.l,bl),L2=Math.min(f.l,bl);const cr=(L1+.05)/(L2+.05);const fs=parseFloat(s.fontSize);const big=fs>=24||(fs>=18.6&&+s.fontWeight>=700);if(cr<(big?3:4.5)){const k=e.tagName+'.'+(e.className||'').toString().split(' ')[0];if(!out.has(k))out.set(k,cr.toFixed(2)+' '+fs+'px '+s.color+' "'+t.slice(0,40)+'"')}}return [...out.entries()].slice(0,12)});
console.log(u);r.forEach(x=>console.log('  ',x.join(' ')))}
await b.close()})();
