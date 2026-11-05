const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.setDefaultTimeout(6000);
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  for (const sel of ['button[data-mode=custom]', '.dsb-card:visible:has-text("Light")', '.dsb-card:visible:has-text("1350 Series")']) { await p.click(sel); await p.waitForTimeout(1300); }
  for (let k = 0; k < 6; k++) {
    const info = await p.evaluate(() => { const s = [...document.querySelectorAll('section[data-step]')].find(e => e.offsetParent); if (!s) return 'none';
      return s.getAttribute('data-step') + ' :: ' + (s.querySelector('h2,h3,legend')?.textContent||'').trim().slice(0,50) + '\n  ' + [...s.querySelectorAll('button,input,select,[data-pick],[data-val],[role=option]')].filter(e => e.offsetParent).slice(0, 12).map(e => e.tagName + '[' + [...e.attributes].filter(a => a.name.startsWith('data-') || a.name==='class' || a.name==='type').map(a => a.name + '=' + a.value.slice(0, 26)).join(' ') + '] ' + (e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30)).join('\n  '); });
    console.log(info);
    const o = p.locator('section[data-step]:visible').locator('[data-pick], [data-val], .dsb-card, .dsb-yoke, button.dsb-opt').first();
    if (!await o.count()) break; await o.click().catch(e => console.log('noclick', e.message.slice(0,80))); await p.waitForTimeout(1500);
  }
  await b.close();
})();
