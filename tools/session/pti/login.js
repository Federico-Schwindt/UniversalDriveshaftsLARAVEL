const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', locale: 'en-US' });
  const p = await ctx.newPage(); const net = [];
  p.on('response', r => { const u = r.url(); if (/powertrain/.test(u) && !/\.(png|jpg|jpeg|gif|webp|svg|css|woff2?|ico)(\?|$)/i.test(u)) net.push(r.status() + ' ' + u.replace('https://c2.powertrainindustries.com', '').slice(0, 110)); });
  await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/', { waitUntil: 'domcontentloaded', timeout: 60000 }); await p.waitForTimeout(1500);
  await p.fill('input[name="username"]', process.env.PTI_USER); await p.fill('input[name="password"]', process.env.PTI_PASS);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}), p.click('button[type="submit"]')]);
  await p.waitForTimeout(2000); console.log('after login:', p.url(), '|', await p.title());
  if (!/driveshaft-designer/.test(p.url())) { await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/', { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(2500); }
  console.log('designer:', p.url(), '|', await p.title());
  const info = await p.evaluate(() => ({
    iframes: [...document.querySelectorAll('iframe')].map(f => f.src),
    scripts: [...document.scripts].map(s => s.src).filter(Boolean).filter(s => !/jquery|google|gtm|facebook|bootstrap|popper/i.test(s)).slice(0, 25),
    images: [...document.images].map(i => i.src).filter(s => !/logo|icon/i.test(s)).slice(0, 40),
    selects: [...document.querySelectorAll('select')].map(s => ({ id: s.id, name: s.name, n: s.options.length, first: [...s.options].slice(0, 8).map(o => o.textContent.trim()) })),
    radios: [...document.querySelectorAll('input[type=radio],input[type=checkbox]')].map(r => r.name + '=' + r.value).slice(0, 30),
    buttons: [...document.querySelectorAll('button,a.btn,input[type=button],input[type=submit],[role=button]')].map(b => (b.textContent || b.value || '').trim().replace(/\s+/g, ' ')).filter(Boolean).slice(0, 40),
    headings: [...document.querySelectorAll('h1,h2,h3,h4')].map(h => h.textContent.trim()).slice(0, 30),
    text: document.body.innerText.replace(/\n+/g, ' / ').slice(0, 2500)
  }));
  console.log(JSON.stringify(info, null, 1)); console.log('NETWORK:\n' + net.slice(0, 40).join('\n'));
  fs.writeFileSync(process.env.SP + '/pti/designer.html', await p.content());
  await p.screenshot({ path: process.env.SP + '/pti/designer.png', fullPage: true });
  await ctx.storageState({ path: process.env.SP + '/pti/state.json' });
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
