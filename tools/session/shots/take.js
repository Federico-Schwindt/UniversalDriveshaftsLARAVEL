const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true }); const B = 'https://dev.udshafts.com'; const out = process.env.SP + '/shots/';
  const shot = async (path, name, opts = {}) => { const p = await b.newPage({ viewport: { width: opts.w || 1400, height: opts.h || 900 }, deviceScaleFactor: 1 });
    await p.goto(B + path, { waitUntil: 'networkidle' }); await p.waitForTimeout(400);
    if (opts.fn) await opts.fn(p);
    await p.screenshot({ path: out + name + '.png', fullPage: !!opts.full, clip: opts.clip }); await p.close(); console.log(name); };
  await shot('/this-does-not-exist', '404', { full: true });
  await shot('/search?q=1310', 'search-1310', { clip: { x: 0, y: 0, width: 1400, height: 1900 } });
  await shot('/search?q=2594-173', 'search-2594', { full: true });
  await shot('/shop/complete-driveshafts', 'hub', { full: true });
  await shot('/shop/complete-driveshafts/by-vehicle', 'by-vehicle', { clip: { x: 0, y: 0, width: 1400, height: 1500 } });
  await shot('/', 'widget', { fn: async (p) => { await p.keyboard.press('/'); await p.waitForTimeout(300); await p.keyboard.type('1310'); await p.waitForTimeout(1400); await p.keyboard.press('ArrowDown'); await p.waitForTimeout(200); } });
  await shot('/', 'widget-idle', { fn: async (p) => { await p.keyboard.press('/'); await p.waitForTimeout(400); } });
  await shot('/catalog/driveshafts?make=Audi&year=2010&model=A4', 'finder-audi', { clip: { x: 0, y: 0, width: 1400, height: 1300 } });
  await shot('/search?q=2594-173', 'search-mobile', { w: 390, h: 800, full: true });
  await shot('/this-does-not-exist', '404-mobile', { w: 390, h: 800, full: true });
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
