const { chromium } = require(process.env.PWC); const fs = require('fs');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--disable-blink-features=AutomationControlled'] });
  const ctx = await b.newContext({ storageState: process.env.SP + '/pti/state.json', viewport: { width: 1400, height: 1000 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  const p = await ctx.newPage(); const api = [];
  p.on('response', async r => { const u = r.url(); if (/powertrain/.test(u) && /json|api|ajax|designer/i.test(u + (r.headers()['content-type'] || '')) && !/\.(png|jpg|css|js)/.test(u)) { let body = ''; try { body = (await r.text()).slice(0, 400); } catch (e) {} api.push(r.status() + ' ' + u.replace('https://c2.powertrainindustries.com', '') + ' :: ' + body.replace(/\s+/g, ' ')); } });
  await p.goto('https://c2.powertrainindustries.com/resources/driveshaft-designer/', { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(1500);
  // save the app bundles for offline analysis
  const scripts = await p.evaluate(() => [...document.scripts].map(s => s.src).filter(s => /assets\/js\/dist/.test(s)));
  for (const s of scripts) { const r = await ctx.request.get(s); fs.writeFileSync(process.env.SP + '/pti/js/' + s.split('/').pop().split('?')[0], await r.text()); }
  const snap = async (tag) => { const t = await p.evaluate(() => { const root = document.querySelector('#designer, .designer, [class*="designer"], main') || document.body; return { text: root.innerText.replace(/\n+/g, ' / ').slice(0, 1200), imgs: [...root.querySelectorAll('img')].map(i => i.src).filter(s => /cdn\.powertrain.*img\/(?!avatars|messages)/.test(s)).slice(0, 25), clickables: [...root.querySelectorAll('a,button,[onclick],[role=button],img[src*="piece"]')].map(e => (e.textContent || e.alt || e.src.split('/').pop() || '').trim().replace(/\s+/g, ' ').slice(0, 40)).filter(Boolean).slice(0, 40) }; }); console.log(`\n##### ${tag}\n` + JSON.stringify(t, null, 1)); await p.screenshot({ path: process.env.SP + '/pti/step-' + tag + '.png', fullPage: true }); };
  await snap('0-start');
  // step 1: choose 1-piece
  const one = await p.$('img[src*="1-piece.png"]'); if (one) { await one.click(); await p.waitForTimeout(2000); await snap('1-onepiece'); }
  console.log('\nAPI calls:\n' + api.slice(0, 30).join('\n'));
  fs.writeFileSync(process.env.SP + '/pti/step1.html', await p.content());
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 300)); process.exit(1); });
