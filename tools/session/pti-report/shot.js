const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 816, height: 1056 } });
  await p.goto('file://' + process.env.D + '/new-parts.html'); await p.screenshot({ path: process.env.D + '/p1.png' }); await b.close(); })();
