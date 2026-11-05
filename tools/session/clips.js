const { chromium } = require(process.env.PWC);
(async () => {
  const browser = await chromium.launch({ headless: true });
  const jobs = [
    ['https://dev.udshafts.com/parts/5-1310XU', 'pdp-desktop2', { width: 1366, height: 900 }, false, [3500]],
    ['https://dev.udshafts.com/series/1310', 'series-phone2', { width: 390, height: 844 }, true, [1600]],
    ['https://dev.udshafts.com/series/1310', 'series-desktop2', { width: 1366, height: 900 }, false, [1800]],
  ];
  for (const [url, name, viewport, mobile, offsets] of jobs) {
    const ctx = await browser.newContext({ viewport, isMobile: mobile, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(500);
    const H = await page.evaluate(() => document.documentElement.scrollHeight);
    for (const y of offsets) {
      if (y >= H) continue;
      await page.screenshot({ path: `${process.env.OUT}/${name}-${y}.png`, fullPage: true, clip: { x: 0, y, width: viewport.width, height: Math.min(1600, H - y) } });
    }
    console.log(name, 'H=' + H);
    await ctx.close();
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
