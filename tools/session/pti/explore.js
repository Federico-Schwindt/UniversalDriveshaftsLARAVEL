const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true }); const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 } }); const p = await ctx.newPage();
  const log = [];
  await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/', { waitUntil: 'networkidle', timeout: 60000 });
  log.push('landed: ' + p.url() + ' | title: ' + await p.title());
  // find a login form if we were redirected
  const hasLogin = await p.$('input[type="password"]');
  if (hasLogin) {
    const userSel = await p.evaluate(() => { const i = [...document.querySelectorAll('input')].find(x => /user|email|login/i.test(x.name + x.id + x.type)); return i ? (i.id ? '#' + i.id : `input[name="${i.name}"]`) : null; });
    log.push('login form found, user field: ' + userSel);
    await p.fill(userSel, process.env.PTI_USER); await p.fill('input[type="password"]', process.env.PTI_PASS);
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }).catch(() => {}), p.keyboard.press('Enter')]);
    log.push('after login: ' + p.url());
    if (!p.url().includes('driveshaft-designer')) await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/', { waitUntil: 'networkidle', timeout: 60000 });
  }
  await p.waitForTimeout(2000);
  log.push('designer url: ' + p.url() + ' | title: ' + await p.title());
  const info = await p.evaluate(() => ({
    iframes: [...document.querySelectorAll('iframe')].map(f => f.src),
    scripts: [...document.scripts].map(s => s.src).filter(Boolean).filter(s => !/jquery|wp-includes|google|gtm|facebook/i.test(s)).slice(0, 30),
    images: [...document.images].map(i => i.src).filter(s => !/logo|icon|wp-content\/themes/i.test(s)).slice(0, 40),
    selects: [...document.querySelectorAll('select')].map(s => ({ id: s.id, name: s.name, n: s.options.length, first: [...s.options].slice(0, 6).map(o => o.textContent.trim()) })),
    buttons: [...document.querySelectorAll('button,a.btn,input[type=button],input[type=submit]')].map(b => (b.textContent || b.value || '').trim()).filter(Boolean).slice(0, 40),
    headings: [...document.querySelectorAll('h1,h2,h3')].map(h => h.textContent.trim()).slice(0, 30),
    bodyText: document.body.innerText.slice(0, 3000)
  }));
  fs.writeFileSync(process.env.SP + '/pti/page.html', await p.content());
  await p.screenshot({ path: process.env.SP + '/pti/designer-full.png', fullPage: true });
  console.log(log.join('\n')); console.log(JSON.stringify(info, null, 1));
  await ctx.storageState({ path: process.env.SP + '/pti/state.json' });
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 400)); process.exit(1); });
