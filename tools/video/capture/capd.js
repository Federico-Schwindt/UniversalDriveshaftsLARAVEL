const { chromium } = require(process.env.PWC); const OUT = process.env.HOME + '/pti-work/reel/cap2/';
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport:{width:1440,height:900}, deviceScaleFactor:2 });
  await ctx.addCookies([{ name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage(); const hide = () => p.addStyleTag({content:'.floaty,.ua-launch{display:none!important}'});
  await p.goto('https://dev.udshafts.com/driveshaft-designer', { waitUntil:'networkidle' }); await hide(); await p.waitForTimeout(800);
  await p.evaluate(() => window.scrollTo(0, 560)); await p.waitForTimeout(500); await p.screenshot({ path: OUT + 'd-1.png' });
  const one = p.locator('a,button').filter({ hasText: /One piece|Choose|Start|Design/i });
  console.log((await one.allTextContents()).map(t=>t.trim().slice(0,40)));
  const link = p.locator('a[href*="/driveshaft-designer/1"]').first(); console.log('link', await link.getAttribute('href').catch(()=>null));
  await link.click().catch(()=>{}); await p.waitForTimeout(2000); await hide(); console.log('url', p.url());
  await p.screenshot({ path: OUT + 'd-2.png' });
  console.log(await p.evaluate(() => [...document.querySelectorAll('main button, main a, main input')].filter(e => e.offsetParent).slice(0,40).map(e => e.tagName + ' ' + (e.getAttribute('data-sec')||e.getAttribute('href')||e.getAttribute('name')||'') + ' | ' + (e.textContent||'').trim().replace(/\s+/g,' ').slice(0,40)).join('\n')));
  await b.close();
})();
