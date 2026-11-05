const { chromium } = require(process.env.PWC);
(async () => { const [,, src, out, w, h] = process.argv;
  const b = await chromium.launch({ headless: true, executablePath: process.env.BX || undefined, args: ['--allow-file-access-from-files'] });
  const p = await b.newPage({ viewport: { width: +w, height: +h }, deviceScaleFactor: +(process.argv[6]||1) });
  await p.goto('file://' + src); await p.waitForTimeout(800);
  await p.screenshot({ path: out, fullPage: true }); await b.close(); })();
