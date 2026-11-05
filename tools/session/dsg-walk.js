const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message.slice(0, 120)));
  await p.goto('https://dev.udshafts.com/driveshaft-designer', { waitUntil: 'networkidle' }); await p.screenshot({ path: process.env.SP + '/dsg-0.png', fullPage: true });
  await p.click('a[href="/driveshaft-designer/1"]'); await p.waitForLoadState('networkidle'); await p.screenshot({ path: process.env.SP + '/dsg-1.png', fullPage: false });
  console.log('front options:', await p.$$eval('.dsg__section', a => a.length), '| choose label:', await p.$eval('.dsg__choose', e => e.innerText.replace(/\n/g, ' ')));
  await p.click('.dsg__section:nth-child(3)'); await p.waitForLoadState('networkidle');
  console.log('after front:', p.url(), '| preview imgs:', await p.$$eval('.dsg__preview-sections img', a => a.length), '| choose:', await p.$eval('.dsg__choose b', e => e.innerText));
  await p.click('.dsg__section:nth-child(1)'); await p.waitForLoadState('networkidle'); await p.waitForSelector('#dsg.is-ready', { timeout: 20000 });
  console.log('result:', p.url(), '| dims:', await p.$$eval('#dsg-svg .dim-text', t => t.map(x => x.getAttribute('name') + ':' + x.textContent.trim()).join(' ')));
  await p.click('#dsg-svg .dim-text[data-tab-index="1"]'); await p.waitForTimeout(200); await p.keyboard.type('52.25'); await p.keyboard.press('Enter'); await p.waitForTimeout(200);
  const after = await p.evaluate(() => ({ hash: location.hash, texts: [...document.querySelectorAll('#dsg-svg .dim-text')].map(t => t.textContent.trim()).join(' '), inputOpenFor: document.getElementById('dsg-dim-input').hidden ? 'closed' : document.getElementById('dsg-dim-input').dataset.target }));
  console.log('after typing 52.25 + Enter:', JSON.stringify(after));
  await p.keyboard.type('3.0'); await p.keyboard.press('Tab'); await p.waitForTimeout(200);
  console.log('after 3.0 + Tab:', await p.evaluate(() => location.hash));
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  await p.screenshot({ path: process.env.SP + '/dsg-result.png', fullPage: false });
  // reload with the hash: values must persist
  await p.reload({ waitUntil: 'networkidle' }); await p.waitForSelector('#dsg.is-ready');
  console.log('after reload:', await p.$$eval('#dsg-svg .dim-text', t => t.map(x => x.textContent.trim()).join(' ')));
  await p.emulateMedia({ media: 'print' }); await p.screenshot({ path: process.env.SP + '/dsg-print.png', fullPage: true }); await p.emulateMedia({ media: 'screen' });
  const m = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 }); await m.goto(p.url(), { waitUntil: 'networkidle' }); await m.waitForSelector('#dsg.is-ready'); await m.screenshot({ path: process.env.SP + '/dsg-phone.png', fullPage: true });
  await m.goto('https://dev.udshafts.com/driveshaft-designer/2/01', { waitUntil: 'networkidle' }); await m.screenshot({ path: process.env.SP + '/dsg-phone-sections.png', fullPage: false });
  console.log('JS errors:', errs.length ? errs.join(' | ') : 'none'); await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
