const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  console.log(JSON.stringify(await p.evaluate(() => {
    const hits = [], sheets = [];
    const walk = (rules, href, media) => { for (const r of rules) { if (r.cssRules) { walk(r.cssRules, href, r.conditionText || media); continue; } if (r.selectorText && /dsb-hero-logo/.test(r.selectorText)) hits.push({ href: (href || 'inline').slice(-14), media, css: r.cssText.slice(0, 150) }); } };
    for (const s of document.styleSheets) { let n = 0; try { n = s.cssRules.length; walk(s.cssRules, s.href, null); } catch (e) { n = 'ERR ' + e.message; } sheets.push({ href: (s.href || 'inline').slice(-30), n }); }
    const el = document.querySelector('.dsb-hero-logo'), cs = getComputedStyle(el);
    return { sheets, hits, computed: { w: cs.width, pad: cs.padding, box: cs.boxSizing, display: cs.display } };
  }), null, 1));
  await b.close(); })();
