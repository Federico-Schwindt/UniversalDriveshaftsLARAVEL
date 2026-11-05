const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => { const ck = JSON.parse(fs.readFileSync(process.env.HOME + '/pti-work/admcookie.json')); const b = await chromium.launch();
  for (const [w, h, f] of [[1280, 800, 'acct-desk'], [390, 844, 'acct-mob']]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: w < 500 ? 2 : 1 });
    await ctx.addCookies([{ name: ck.name, value: ck.value, domain: 'dev.udshafts.com', path: '/', httpOnly: true, secure: true }]);
    const p = await ctx.newPage(); await p.goto('https://dev.udshafts.com/account/orders?q=4471', { waitUntil: 'networkidle' });
    const el = await p.$('.acct-main'); await el.screenshot({ path: process.env.HOME + '/pti-work/' + f + '.png' }); await ctx.close(); }
  await b.close(); })();
