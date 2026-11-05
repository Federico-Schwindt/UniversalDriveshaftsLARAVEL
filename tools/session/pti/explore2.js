const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', locale: 'en-US' });
  const p = await ctx.newPage();
  for (const u of ['https://c2.powertrainindustries.com/', 'https://c2.powertrainindustries.com/resources/driveshaft-designer/']) {
    const r = await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 60000 }); await p.waitForTimeout(2500);
    console.log(u, '->', r.status(), '|', await p.title(), '|', p.url());
  }
  const t = await p.evaluate(() => document.body.innerText.slice(0, 1500)); console.log('BODY:', t.replace(/\n+/g, ' / ').slice(0, 900));
  console.log('inputs:', await p.evaluate(() => [...document.querySelectorAll('input,select,button')].map(e => e.tagName + ':' + (e.type || '') + ':' + (e.name || e.id || e.textContent.trim().slice(0, 20))).slice(0, 25).join(' | ')));
  fs.writeFileSync(process.env.SP + '/pti/page2.html', await p.content()); await p.screenshot({ path: process.env.SP + '/pti/shot2.png', fullPage: false });
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
