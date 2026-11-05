const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); 
 for (const l of ['en','fr']) { const p = await b.newPage({ viewport: { width: 390, height: 844 } });
 await p.goto('https://dev.udshafts.com/shop?lang='+l, { waitUntil: 'networkidle' });
 console.log(l, await p.evaluate(() => { let e=document.querySelector('h1'), out=[]; while(e && e!==document.body){ const s=getComputedStyle(e); out.push(e.tagName+'.'+String(e.className).slice(0,25)+' w='+Math.round(e.getBoundingClientRect().width)+' disp='+s.display+' gtc='+s.gridTemplateColumns.slice(0,40)+' ov='+s.overflowX); e=e.parentElement;} return out.join('\n   '); })); }
 await b.close(); })();
