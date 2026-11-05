const { chromium } = require(process.env.PWC);
(async () => {
  const browser = await chromium.launch({ headless: true });
  for (const [name, vp, scale] of [['phone', { width: 390, height: 844 }, 2], ['desktop', { width: 1380, height: 900 }, 1]]) {
    const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: scale, isMobile: vp.width < 500 });
    const page = await ctx.newPage();
    await page.goto('https://dev.udshafts.com/login', { waitUntil: 'networkidle' });
    await page.fill('input[name=email]', 'uds-buyagain-test@example.com');
    await page.fill('input[name=password]', process.env.UDS_QA_PASSWORD);
    await page.press('input[name=password]', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.goto('https://dev.udshafts.com/account/buy-again', { waitUntil: 'networkidle' });
    await page.locator('.apanel').nth(1).screenshot({ path: `${name}-orders2.png` });
    await ctx.close();
  }
  await browser.close();
})();
