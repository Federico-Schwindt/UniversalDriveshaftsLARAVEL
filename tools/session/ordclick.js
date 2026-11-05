const { chromium } = require(process.env.PWC);
const fs = require('fs');
(async () => {
  const S = process.env.S, pw = fs.readFileSync(S + '/qa.txt', 'utf8').trim();
  const browser = await chromium.launch({ headless: true });
  for (const [name, o] of [['desk',{viewport:{width:1440,height:900}}],['mob',{viewport:{width:375,height:812},isMobile:true,hasTouch:true}]]) {
    const ctx = await browser.newContext({ ...o, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    const errs=[]; page.on('pageerror', e => errs.push(e.message));
    await page.goto('https://dev.udshafts.com/login', { waitUntil: 'networkidle' });
    await page.fill('input[name=email]', 'qa-admin@udshafts.local'); await page.fill('input[name=password]', pw);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('form[action="/login"] [type=submit]')]);
    await page.goto('https://dev.udshafts.com/admin/orders', { waitUntil: 'networkidle' });
    const n = await page.locator('tr[data-href]').count();
    const target = page.locator('tr[data-href] td.primary').first();
    const box = await target.boundingBox();
    const hit = await page.evaluate(b => { const e=document.elementFromPoint(b.x+b.width/2,b.y+b.height/2); return e && (e.tagName+'.'+e.className); }, box);
    if (o.hasTouch) await target.tap(); else await target.click();
    await page.waitForTimeout(1500);
    console.log(name, 'rows', n, 'hit', hit, 'url', page.url(), 'errs', errs.join('|'));
    await ctx.close();
  }
  await browser.close();
})();
