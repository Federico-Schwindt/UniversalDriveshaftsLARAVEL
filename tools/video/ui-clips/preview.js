const { chromium } = require(process.env.PWC); const path = require('path');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto('file://' + path.join(__dirname, 'video/fonts.html'));
  await p.addScriptTag({ path: path.join(__dirname, 'video/ui.js') });
  await p.evaluate(() => document.fonts.ready);
  for (const k of ['home', 'finder', 'designer', 'assistant', 'account', 'sup1']) {
    await p.evaluate((k) => { document.body.innerHTML = window.UI[k]; const s = document.querySelector('svg'); if (k === 'home') s.querySelector('#drop').setAttribute('opacity', 1); if (k === 'finder') s.querySelector('#result').setAttribute('opacity', 1); }, k);
    await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
    const el = await p.$('svg'); await el.screenshot({ path: path.join(__dirname, 'out/prev-' + k + '.png') });
  }
  await b.close();
})();
