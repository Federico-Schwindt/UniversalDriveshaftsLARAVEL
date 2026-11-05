const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await ctx.addCookies([{ name: 'uds_lang', value: 'en', domain: 'dev.udshafts.com', path: '/' }]);
  const p = await ctx.newPage(); await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' }); await p.addStyleTag({ content: '.floaty,.ua-launch{display:none!important}' }); await p.waitForTimeout(1000);
  await p.screenshot({ path: '/tmp/claude-10004/-var-www-vhosts-dev-udshafts-com-uds-app/2d80c33c-fa7e-44a8-b91c-6de800ec3bee/scratchpad/b0.png' });
  console.log(await p.evaluate(() => [...document.querySelectorAll('main button, main a, main input, main [data-step], main [role=button]')].filter(e => e.offsetParent).slice(0, 50).map(e => { const r = e.getBoundingClientRect(); return `${e.tagName} ${[...e.attributes].filter(a => a.name.startsWith('data-') || a.name === 'name' || a.name === 'href').map(a => a.name + '=' + a.value.slice(0, 30)).join(' ')} @${r.x | 0},${r.y | 0} | ${(e.textContent || e.value || '').trim().replace(/\s+/g, ' ').slice(0, 40)}`; }).join('\n')));
  await b.close();
})();
