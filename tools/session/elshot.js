// usage: node elshot.js <file.html> <selector> <out.png> <width> [pad]
const { chromium } = require(process.env.PWC);
(async () => { const [,, src, sel, out, w, pad] = process.argv;
  const b = await chromium.launch({ headless: true, executablePath: process.env.BX || undefined, args: ['--no-sandbox'] });
  const p = await b.newPage({ viewport: { width: +w, height: 900 } });
  await p.goto('file://' + src, { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(600);
  const el = await p.$(sel); const bb = await el.boundingBox(); const P = +(pad || 30);
  await p.screenshot({ path: out, clip: { x: Math.max(0, bb.x - P), y: Math.max(0, bb.y - P), width: Math.min(+w, bb.width + 2 * P), height: bb.height + 2 * P }, fullPage: true });
  await b.close(); })();
