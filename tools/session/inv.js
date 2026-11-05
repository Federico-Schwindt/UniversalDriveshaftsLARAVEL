const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch(); const c = await b.newContext({ viewport:{width:900,height:1200}, deviceScaleFactor:2 });
  const ck=JSON.parse(require('fs').readFileSync(process.env.HOME+'/pti-work/reel/cookie.json','utf8')); await c.addCookies(Array.isArray(ck)?ck:[ck]);
  const p = await c.newPage(); const r = await p.goto('https://dev.udshafts.com/admin/orders/32/invoice', {waitUntil:'networkidle'});
  console.log('HTTP', r.status());
  await p.screenshot({ path: process.argv[2], fullPage: true }); await b.close();
})();
