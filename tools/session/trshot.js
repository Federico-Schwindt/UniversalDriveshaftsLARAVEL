// usage: node trshot.js <dashboard.html> <traffic.json> <out.png>
const { chromium } = require(process.env.PWC);
const fs = require('fs');
(async () => { const [,, html, json, out] = process.argv;
  const b = await chromium.launch({ headless: true, executablePath: process.env.BX || undefined, args: ['--no-sandbox', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const p = await b.newPage({ viewport: { width: 1440, height: 1100 } });
  const body = fs.readFileSync(json, 'utf8');
  await p.route('**/admin/traffic/**', r => r.fulfill({ status: 200, contentType: 'application/json', body }));
  await p.route('**/data/countries-110m.geojson', r => r.fulfill({ status: 200, contentType: 'application/json', body: fs.readFileSync('/var/www/vhosts/dev.udshafts.com/uds-app/public/data/countries-110m.geojson', 'utf8') }));
  p.on('console', m => { if (m.type() === 'error') console.log('console:', m.text().slice(0, 200)); });
  await p.goto('file://' + html, { waitUntil: 'networkidle', timeout: 90000 });
  await p.waitForTimeout(5000);
  if (process.argv[5] === 'click') { await p.click('[data-tr-list] button[data-i="0"]'); await p.waitForTimeout(2500); }
  const el = await p.$('[data-traffic]'); const bb = await el.boundingBox();
  await p.screenshot({ path: out, clip: { x: bb.x, y: bb.y, width: bb.width, height: bb.height }, fullPage: true });
  await b.close(); })();
