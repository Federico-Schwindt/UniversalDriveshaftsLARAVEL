const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch();
 for (const [w,label] of [[1366,'desktop'],[390,'mobile']]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  const hero = [];
  p.on('response', r => { const u=r.url(); if(/hero-ujoint/.test(u)) hero.push([u.split('/').pop(), r.request().resourceType()]); });
  await p.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' });
  const cur = await p.evaluate(() => { const i=document.querySelector('.n-hero__part img'); return i ? i.currentSrc.split('/').pop() : 'none'; });
  console.log(label+': browser loaded', JSON.stringify(hero.map(h=>h[0])), '| <img> currentSrc =', cur);
  await p.close();
 }
 await b.close(); })();
