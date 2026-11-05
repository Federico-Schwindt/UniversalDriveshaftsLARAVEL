const { chromium } = require(process.env.PWC);
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 390, height: 1100 }, isMobile: true, hasTouch: true });
  await page.goto('https://dev.udshafts.com/driveshafts/chevrolet/silverado-1500#y2015', { waitUntil: 'networkidle', timeout: 60000 });
  await page.evaluate(() => document.getElementById('veh-count').scrollIntoView());
  await page.waitForTimeout(400);
  console.log(await page.evaluate(() => document.getElementById('veh-count').textContent + ' | visible rows ' + document.querySelectorAll('#veh-table tbody tr:not(.is-hidden)').length));
  await page.screenshot({ path: 'silv-phone-table.png' });
  await browser.close();
})();
