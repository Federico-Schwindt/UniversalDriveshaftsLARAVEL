const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch(); const pg=await b.newPage();
 for (const p of process.argv.slice(2)) { await pg.goto('https://dev.udshafts.com'+p+'?lang=es',{waitUntil:'networkidle'});
  const t=await pg.evaluate(()=>(document.querySelector('main')||document.body).innerText);
  const lines=t.split('\n').filter(l=>/\b(the|and|with|your|you|this|that|from|shaft|joint|measure|bearing|order|for)\b/i.test(l));
  console.log('== '+p); lines.forEach(l=>console.log('  '+l.slice(0,160))); }
 await b.close(); })();
