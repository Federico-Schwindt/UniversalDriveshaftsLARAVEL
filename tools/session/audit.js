const { chromium } = require(process.env.PWC);
const pages = ['/about','/services','/heavy-duty-driveshafts','/industrial-driveshafts','/custom-off-road-driveshafts','/pto-agriculture','/wholesale','/contact','/guides','/no-such-page-xyz',
 ...['how-to-measure-a-driveshaft','u-joint-series-identification-chart','driveshaft-vibration-and-noise-diagnosis','slip-yoke-vs-weld-yoke-vs-flange-yoke','center-support-bearing-replacement','choosing-driveshaft-tubing','custom-driveshaft-cost-and-lead-time','one-piece-vs-two-piece-driveshaft','driveshaft-angles-and-pinion-angle','replace-or-repair-a-driveshaft'].map(s=>'/guides/'+s)];
(async()=>{
  const b = await chromium.launch();
  for (const lang of ['es','en']) for (const w of [1280,390]) {
    const ctx = await b.newContext({ viewport:{width:w,height:900}, ignoreHTTPSErrors:true });
    const pg = await ctx.newPage(); let errs=[];
    pg.on('console', m=>{ if(m.type()==='error') errs.push(m.text().slice(0,140)); });
    pg.on('pageerror', e=>errs.push('PAGEERR '+e.message.slice(0,140)));
    for (const p of pages) {
      errs=[];
      const r = await pg.goto('https://dev.udshafts.com'+p+'?lang='+lang, {waitUntil:'networkidle', timeout:45000}).catch(e=>null);
      const info = await pg.evaluate(()=>{
        const de=document.documentElement; const over = de.scrollWidth - de.clientWidth;
        const main = document.querySelector('main') || document.body;
        return { over, title: document.title, text: main.innerText, lang: de.lang };
      });
      let flag='';
      if (lang==='es' && w===1280) {
        // crude English detector on main content
        const hits = (info.text.match(/\b(the|and|with|your|you|this|that|from|shaft|joint|measure|bearing|order)\b/gi)||[]);
        flag = ' EN-words:'+hits.length+' ['+[...new Set(hits.map(h=>h.toLowerCase()))].slice(0,8).join(',')+']';
      }
      console.log(`${lang} ${w} ${r?r.status():'ERR'} ${p} over=${info.over}${flag} errs=${errs.length}${errs.length?' '+errs.join(' | '):''} | ${info.title}`);
    }
    await ctx.close();
  }
  await b.close();
})();
