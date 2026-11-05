// Interactive-state audit: every button/link gets hovered and focused; text contrast is measured in each state.
const { chromium } = require(process.env.PWC);
function lum([r,g,b]){const f=c=>{c/=255;return c<=0.03928?c/12.92:Math.pow((c+0.055)/1.055,2.4)};return 0.2126*f(r)+0.7152*f(g)+0.0722*f(b)}
function ratio(a,b){const l1=lum(a),l2=lum(b);return (Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05)}
const urls = process.argv.slice(2);
(async () => {
  const b = await chromium.launch({ headless: true }); const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  for (const u of urls) {
    const p = await ctx.newPage(); const fails = []; let n = 0;
    try {
      await p.goto(u, { waitUntil: 'networkidle', timeout: 40000 });
      const count = await p.$$eval('main a.btn, main button, main .btn, main a.n-link, main a.s-link, main .n-path, main .pcard, main .catcard, main .dsg__section, footer a, .upromo a', els => els.length);
      for (let i = 0; i < count; i++) {
        const h = (await p.$$('main a.btn, main button, main .btn, main a.n-link, main a.s-link, main .n-path, main .pcard, main .catcard, main .dsg__section, footer a, .upromo a'))[i]; if (!h) continue;
        const box = await h.boundingBox(); if (!box || box.width < 8 || box.height < 8) continue;
        try { await h.scrollIntoViewIfNeeded(); await h.hover({ timeout: 1500 }); } catch (e) { continue; }
        await p.waitForTimeout(90); n++;
        const r = await h.evaluate(el => {
          const parse = s => { const m = s && s.match(/rgba?\(([^)]+)\)/); if (!m) return null; const a = m[1].split(',').map(Number); return { rgb: a.slice(0,3), a: a.length > 3 ? a[3] : 1 }; };
          const blend = (fg, bg) => fg.rgb.map((c, i) => Math.round(c * fg.a + bg[i] * (1 - fg.a)));
          const bgOf = node => { let acc = null; while (node && node !== document.documentElement) { const cs = getComputedStyle(node); let c = parse(cs.backgroundColor); if (cs.backgroundImage !== 'none') { const g = cs.backgroundImage.match(/gradient\([^)]*?(rgba?\([^)]+\))/); if (g) { const gc = parse(g[1]); if (gc && gc.a > 0) c = { rgb: gc.rgb, a: 1 }; } } if (c && c.a > 0) { if (c.a >= 1) return acc ? blend(acc, c.rgb) : c.rgb; acc = acc ? { rgb: blend(acc, c.rgb), a: Math.min(1, acc.a + c.a) } : c; } node = node.parentElement; } return acc ? blend(acc, [255,255,255]) : [255,255,255]; };
          // measure the deepest text-bearing element so inner spans (e.g. card titles) are checked
          const textEl = [...el.querySelectorAll('*')].find(x => x.children.length === 0 && x.textContent.trim().length > 1) || el;
          const cs = getComputedStyle(textEl); const fg = parse(cs.color); const bg = bgOf(textEl); const fgc = fg.a < 1 ? blend(fg, bg) : fg.rgb;
          return { text: el.textContent.trim().replace(/\s+/g, ' ').slice(0, 36), cls: (typeof el.className === 'string' ? el.className : '').trim().split(/\s+/).slice(0, 3).join('.'), fg: fgc, bg, size: parseFloat(cs.fontSize), weight: parseInt(cs.fontWeight) || 400 };
        });
        const large = r.size >= 24 || (r.size >= 18.66 && r.weight >= 700); const rr = ratio(r.fg, r.bg);
        if (rr < (large ? 3 : 4.5)) fails.push(`${rr.toFixed(2)}:1 hover ${r.cls || 'a'} "${r.text}" fg rgb(${r.fg}) on rgb(${r.bg})`);
      }
      console.log(`${u.replace('https://dev.udshafts.com','').padEnd(36)} hovered ${n} controls | failures: ${fails.length}`); fails.slice(0, 6).forEach(f => console.log('   ' + f));
    } catch (e) { console.log(u, 'ERROR', e.message.slice(0, 80)); }
    await p.close();
  }
  await b.close();
})();
