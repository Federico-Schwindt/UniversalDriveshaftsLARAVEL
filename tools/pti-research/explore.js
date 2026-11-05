const { chromium } = require(process.env.PWC); const fs = require('fs');
const OUT = process.env.OUT;
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); const net = [];
  p.on('response', async r => { const u = r.url(); const ct = r.headers()['content-type'] || '';
    if (/powertrain/.test(u) && /json/.test(ct)) { let body = ''; try { body = (await r.text()).slice(0, 600); } catch {} net.push(r.request().method() + ' ' + r.status() + ' ' + u + '\n   ' + body.replace(/\s+/g, ' ')); } });
  await p.goto('https://c2.powertrainindustries.com/auth', { waitUntil: 'domcontentloaded', timeout: 60000 }); await p.waitForTimeout(1500);
  await p.fill('input[name="username"]', process.env.PTI_USER); await p.fill('input[name="password"]', process.env.PTI_PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}), p.click('button[type="submit"]')]);
  console.log('after login:', p.url());
  for (const path of ['/catalogs/component-parts/types', '/catalogs/small-parts/types']) {
    await p.goto('https://c2.powertrainindustries.com' + path, { waitUntil: 'networkidle', timeout: 60000 });
    const links = await p.evaluate(() => [...document.querySelectorAll('a')].map(a => a.textContent.trim().replace(/\s+/g, ' ') + ' => ' + a.getAttribute('href')).filter(s => /catalogs/.test(s)));
    console.log('== ' + path + '\n' + [...new Set(links)].join('\n'));
  }
  await ctx.storageState({ path: OUT + '/state.json' });
  console.log('JSON calls:\n' + net.join('\n'));
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
