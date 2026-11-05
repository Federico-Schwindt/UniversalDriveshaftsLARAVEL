const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 390, height: 844 } }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto('https://dev.udshafts.com/shop?lang=fr', { waitUntil: 'networkidle' }); await p.waitForTimeout(600);
 console.log('url after:', p.url(), '| toast:', await p.locator('[data-aitr]').isVisible(), '| errors:', errs.join('|')||'none');
 await p.screenshot({ path: process.env.HOME + '/pti-work/fr-shop.png' });
 await p.reload({ waitUntil: 'networkidle' }); console.log('toast after reload:', await p.locator('[data-aitr]').count());
 await b.close(); })();
