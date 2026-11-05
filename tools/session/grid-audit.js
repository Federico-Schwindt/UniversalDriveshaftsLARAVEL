// Grid discipline: at 1440px, any element that holds text or cards but starts left of the site grid
// (or ends right of it) without being a full-bleed band (viewport-wide WITH a background) is reported.
const { chromium } = require(process.env.PWC);
const urls = ['/', '/shop', '/shop/driveshaft-components', '/shop/driveshaft-components/u-joints', '/parts/027SG80002', '/catalog/driveshafts', '/catalog/driveshafts/part/0844-1', '/online-builder', '/online-builder/two-piece', '/premade-driveshafts', '/custom-off-road-driveshafts', '/heavy-duty-driveshafts', '/industrial-driveshafts', '/pto-agriculture', '/services', '/about-us', '/contact', '/cart', '/login', '/register', '/wholesale', '/quick-order', '/track-order', '/rma', '/return-policy', '/terms', '/privacy-policy', '/wishlist', '/search?q=yoke', '/copyright'];
(async () => {
  const b = await chromium.launch({ headless: true }); const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  for (const u of urls) {
    const p = await ctx.newPage();
    try {
      await p.goto('https://dev.udshafts.com' + u, { waitUntil: 'networkidle', timeout: 40000 });
      const r = await p.evaluate(() => {
        const W = innerWidth, wrap = document.querySelector('main .wrap, main .n-wrap'); if (!wrap) return { noWrap: true };
        const wr = wrap.getBoundingClientRect(); const L = wr.left, R = wr.right; const out = [];
        for (const el of document.querySelectorAll('main *')) {
          const b = el.getBoundingClientRect(); if (b.width < 40 || b.height < 20) continue;
          const cs = getComputedStyle(el); if (cs.position === 'fixed') continue;
          const hasBg = cs.backgroundColor !== 'rgba(0, 0, 0, 0)' || cs.backgroundImage !== 'none';
          const fullBleed = b.width >= W - 2;
          const tag = el.tagName.toLowerCase();
          if (['section', 'header', 'footer', 'main', 'nav', 'picture'].includes(tag)) continue;
          if (fullBleed && hasBg) continue;                 // a band
          if (b.left < L - 6 || b.right > R + 6) {
            if (fullBleed && !el.textContent.trim()) continue; // decorative media wrapper
            out.push(`${tag}.${(typeof el.className === 'string' ? el.className.trim().split(/\s+/)[0] : '') || '-'} [${Math.round(b.left)}..${Math.round(b.right)}]`);
            if (out.length > 5) break;
          }
        }
        // also: the widest text container narrower than the grid (content not using the grid)
        return { gridL: Math.round(L), gridR: Math.round(R), out };
      });
      console.log(`${u.padEnd(40)} grid ${r.gridL}-${r.gridR}  ${r.out && r.out.length ? 'OFF-GRID: ' + r.out.slice(0, 4).join(' ; ') : (r.noWrap ? 'no .wrap in main' : 'ok')}`);
    } catch (e) { console.log(`${u.padEnd(40)} ERROR ${e.message.slice(0, 60)}`); }
    await p.close();
  }
  await b.close();
})();
