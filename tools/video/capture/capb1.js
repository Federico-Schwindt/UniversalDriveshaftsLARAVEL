const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.setDefaultTimeout(6000);
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  await p.click('button[data-mode=custom]'); await p.waitForTimeout(1200);
  for (let k = 0; k < 3; k++) {
    const info = await p.evaluate(() => { const s = [...document.querySelectorAll('section[data-step]')].find(e => e.offsetParent); if (!s) return 'none';
      return s.getAttribute('data-step') + ' :: ' + [...s.querySelectorAll('button,input,select')].filter(e => e.offsetParent).slice(0, 14).map(e => e.tagName + '[' + [...e.attributes].filter(a => a.name.startsWith('data-') || a.name==='class' || a.name==='type').map(a => a.name + '=' + a.value.slice(0, 24)).join(' ') + '] ' + (e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30)).join('\n  '); });
    console.log(info);
    const o = p.locator('section[data-step]:visible button:not(:has-text("Back"))').first(); await o.click().catch(e => console.log('noclick')); await p.waitForTimeout(1200);
  }
  await b.close();
})();
