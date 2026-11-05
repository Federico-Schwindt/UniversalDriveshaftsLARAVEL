const { chromium } = require(process.env.PWC);
const fs = require('fs');
(async () => {
  const S = process.env.S, pw = fs.readFileSync(S + '/qa.txt', 'utf8').trim();
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport:{width:1440,height:900}, ignoreHTTPSErrors:true });
  const page = await ctx.newPage();
  await page.goto('https://dev.udshafts.com/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', 'qa-admin@udshafts.local'); await page.fill('input[name=password]', pw);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('form[action="/login"] [type=submit]')]);
  await page.goto('https://dev.udshafts.com/admin/orders/24', { waitUntil: 'networkidle' });
  for (const y of [0, 600, 1500]) {
    await page.evaluate(y => window.scrollTo(0, y), y); await page.waitForTimeout(200);
    console.log(page.url(), y, await page.evaluate(() => { const s=document.querySelector('.adm-side')||document.body; const r=s.getBoundingClientRect(); const cs=getComputedStyle(s); return JSON.stringify({top:r.top,h:r.height,pos:cs.position,docH:document.documentElement.scrollHeight, sy:scrollY, ov:getComputedStyle(document.body).overflowX+'/'+getComputedStyle(document.documentElement).overflowX}); }));
  }
  await browser.close();
})();
