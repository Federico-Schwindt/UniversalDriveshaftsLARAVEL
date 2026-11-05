const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  const out = process.argv[2]; const urls = process.argv.slice(3);
  for (const u of urls) {
    const name = u.replace(/https?:\/\/[^/]+\//, '').replace(/[^a-z0-9]+/gi, '_') || 'home';
    for (const [tag, vp] of [['desk', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true }]]) {
      const ctx = await b.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: vp.deviceScaleFactor || 1, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch });
      const p = await ctx.newPage();
      await p.goto(u, { waitUntil: 'networkidle', timeout: 60000 });
      await p.waitForTimeout(500);
      const sw = await p.evaluate(() => document.documentElement.scrollWidth); const cw = await p.evaluate(() => document.documentElement.clientWidth);
      console.log(`${tag} ${u} scrollWidth=${sw} clientWidth=${cw}${sw > cw ? '  <-- HORIZONTAL OVERFLOW' : ''}`);
      await p.screenshot({ path: `${out}/${name}-${tag}.png`, fullPage: true });
      await ctx.close();
    }
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
