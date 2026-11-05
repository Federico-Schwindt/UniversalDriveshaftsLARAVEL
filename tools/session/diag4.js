const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  console.log(JSON.stringify(await p.evaluate(() => {
    const el = document.querySelector('.dsb-hero-logo'); const hits = [];
    const walk = (rules, href, media) => { for (const r of rules) {
      if (r.selectorText) { try { if (el.matches(r.selectorText) && (r.style.width || r.style.minWidth || r.style.flex || r.style.maxWidth)) hits.push({ href: (href || 'inline').slice(-12), media: media || '-', sel: r.selectorText.slice(0, 50), width: r.style.width + (r.style.getPropertyPriority('width') ? ' !important' : ''), minW: r.style.minWidth, maxW: r.style.maxWidth, flex: r.style.flex }); } catch (e) {} }
      else if (r.cssRules) { if (r.media && !matchMedia(r.media.mediaText).matches) continue; walk(r.cssRules, href, r.conditionText || media); } } };
    for (const s of document.styleSheets) { try { walk(s.cssRules, s.href, null); } catch (e) {} }
    const before = getComputedStyle(el).width; el.style.width = '44px'; const after = getComputedStyle(el).width; el.style.width = '';
    return { before, inlineTest: after, hits };
  }), null, 1));
  await b.close(); })();
