const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
 await p.goto('https://dev.udshafts.com/shop?lang=fr', { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
 console.log(await p.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth, scrollX, getComputedStyle(document.querySelector('h1')).width]));
 await p.screenshot({ path: process.env.HOME + '/pti-work/fr-shop2.png' }); await b.close(); })();
