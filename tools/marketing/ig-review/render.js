// usage: node render.js <dir> s1 s2 s3  → <dir>/<name>.png at 1080×1350, after fonts and images have loaded
const { chromium } = require(process.env.PWC);
(async () => { const [,, dir, ...names] = process.argv;
  const b = await chromium.launch({ headless: true, executablePath: process.env.BX || undefined, args: ['--no-sandbox', '--allow-file-access-from-files'] });
  const p = await b.newPage({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 1 });
  for (const n of names) {
    await p.goto('file://' + dir + '/' + n + '.html', { waitUntil: 'networkidle', timeout: 60000 });
    await p.evaluate(async () => { await document.fonts.ready; await Promise.all([...document.images].map(i => i.complete ? 1 : new Promise(r => { i.onload = i.onerror = r; }))); });
    const ok = await p.evaluate(() => document.fonts.check('800 40px "Barlow Condensed"'));
    await p.screenshot({ path: dir + '/' + n + '.png', clip: { x: 0, y: 0, width: 1080, height: 1350 } });
    console.log(n, 'fonts', ok ? 'ok' : 'FALLBACK');
  }
  await b.close(); })();
