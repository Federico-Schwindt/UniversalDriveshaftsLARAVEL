const { chromium } = require(process.env.PWC); const OUT = process.env.HOME + '/pti-work/reel/cap2/'; const fs=require('fs');
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport:{width:1440,height:900}, deviceScaleFactor:2 });
  await ctx.addCookies([{ name:'uds_lang', value:'en', domain:'dev.udshafts.com', path:'/' }]);
  const p = await ctx.newPage(); const meta={};
  await p.goto('https://dev.udshafts.com/driveshaft-designer/1/01+01R', { waitUntil:'networkidle' }); await p.addStyleTag({content:'.floaty,.ua-launch{display:none!important}'}); await p.waitForTimeout(1500); await p.evaluate(() => document.querySelectorAll('#dsg-svg text').forEach(t => { if (/FRONT|REAR/.test(t.textContent)) t.style.display = 'none'; }));
  const ins = await p.evaluate(() => [...document.querySelectorAll('main input')].filter(e => e.offsetParent).map(e => { const r=e.getBoundingClientRect(); return { name: e.name||e.id, ph: e.placeholder, y: r.y+scrollY }; }));
  console.log(JSON.stringify(ins));
  const svg = p.locator('#dsg-svg'); const sb = await svg.boundingBox(); await p.evaluate((y) => window.scrollTo(0, Math.max(0, y + scrollY - 150)), sb.y);
  await p.waitForTimeout(700); await p.screenshot({ path: OUT + 'd-m0.png' });
  const vals = ['52.25', '48.5', '3.5']; let k = 0;
  for (let i = 0; i < 3; i++) {
    const lab = p.locator('#dsg-svg .dim-text').nth(i); const r = await lab.boundingBox();
    meta['tap' + i] = r ? { x: r.x + r.width/2, y: r.y + r.height/2, at: k + 1 } : null;
    if (i === 0) await lab.click(); await p.waitForTimeout(250); await p.screenshot({ path: OUT + `d-m${++k}.png` });
    for (const ch of vals[i]) { await p.keyboard.type(ch); await p.waitForTimeout(100); await p.screenshot({ path: OUT + `d-m${++k}.png` }); }
    await p.keyboard.press('Enter'); await p.waitForTimeout(300); await p.screenshot({ path: OUT + `d-m${++k}.png` });
  }
  await p.waitForTimeout(800); await p.screenshot({ path: OUT + `d-m${++k}.png` });
  fs.writeFileSync(OUT + 'meta-d.json', JSON.stringify({ count: k, meta }));
  console.log('frames', k); await b.close();
})();
