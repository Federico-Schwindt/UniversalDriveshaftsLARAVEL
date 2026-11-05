const { chromium } = require(process.env.PWC); const OUT = process.env.HOME + '/pti-work/reel/cap2/';
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport:{width:1440,height:900}, deviceScaleFactor:2 });
  await ctx.addCookies([{ name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage();
  for (const u of ['/driveshaft-designer/1/01', '/driveshaft-designer/1/01+01']) {
    await p.goto('https://dev.udshafts.com' + u, { waitUntil:'networkidle' }); await p.addStyleTag({content:'.floaty,.ua-launch{display:none!important}'}); await p.waitForTimeout(1200);
    await p.screenshot({ path: OUT + 'd-' + u.split('/').pop().replace('+','_') + '.png' });
    console.log(u, await p.evaluate(() => [...document.querySelectorAll('main input, main button')].filter(e => e.offsetParent).slice(0,25).map(e => e.tagName + ' ' + (e.getAttribute('name')||e.getAttribute('data-k')||e.id||'') + ' | ' + (e.textContent||e.placeholder||'').trim().replace(/\s+/g,' ').slice(0,30)).join('\n')));
  }
  await b.close();
})();
