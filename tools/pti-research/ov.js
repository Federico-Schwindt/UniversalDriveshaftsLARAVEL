const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); 
 for (const l of ['en','es','fr']) for (const u of ['/','/shop','/services','/guides','/cart','/track-order','/contact']) { const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
  await ctx.addCookies([{name:'uds_lang',value:l,domain:'dev.udshafts.com',path:'/'}]); const p = await ctx.newPage();
  await p.goto('https://dev.udshafts.com'+u, { waitUntil: 'domcontentloaded' });
  const r = await p.evaluate(() => { const W=document.documentElement.clientWidth; const bad=[...document.querySelectorAll('body *')].filter(e=>{const x=e.getBoundingClientRect(); return x.right>W+1 && x.width>0 && getComputedStyle(e).position!=='fixed'}).slice(0,3).map(e=>e.tagName+'.'+(e.className||'').toString().slice(0,30)+':'+Math.round(e.getBoundingClientRect().right)); return [document.documentElement.scrollWidth, bad]; });
  if (r[0] > 391) console.log(l, u, r[0], r[1].join(' ')); await ctx.close(); }
 console.log('done'); await b.close(); })();
