// usage: node urlshot.js <url> <out.png> <width> <height> [clipHeight]
const { chromium } = require(process.env.PWC);
(async () => { const [,, url, out, w, h, clip] = process.argv;
  const b = await chromium.launch({ headless: true, executablePath: process.env.BX || undefined, args: ['--no-sandbox'] });
  const p = await b.newPage({ viewport: { width: +w, height: +h } });
  await p.goto(url, { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(800);
  const t = await p.$('.ptbl'); if (t) await t.scrollIntoViewIfNeeded();
  const y = t ? (await t.boundingBox()).y + (await p.evaluate(() => scrollY)) - 80 : 0;
  await p.screenshot({ path: out, clip: { x: 0, y: Math.max(0, y), width: +w, height: +(clip || h) }, fullPage: true }); await b.close(); })();
