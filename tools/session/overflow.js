const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const S = process.env.S, pw = fs.readFileSync(S + '/qa.txt', 'utf8').trim();
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 375, height: 812 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('https://dev.udshafts.com/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', 'qa-admin@udshafts.local'); await page.fill('input[name=password]', pw);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('form[action="/login"] [type=submit]')]);
  for (const p of ['/admin', '/admin/orders/9']) {
    await page.goto('https://dev.udshafts.com' + p, { waitUntil: 'networkidle' });
    const out = await page.evaluate(() => {
      const W = document.documentElement.clientWidth, res = [];
      document.querySelectorAll('body *').forEach(el => { const r = el.getBoundingClientRect(); if (r.right > W + 1 && r.width > 0 && true) res.push((el.className && typeof el.className === 'string' ? el.tagName + '.' + el.className.split(' ').slice(0, 2).join('.') : el.tagName) + ' right=' + Math.round(r.right) + ' w=' + Math.round(r.width)); });
      return { sw: document.documentElement.scrollWidth, cw: W, top: res.slice(0, 12) };
    });
    console.log(p, JSON.stringify(out, null, 1));
  }
  await browser.close();
})();
