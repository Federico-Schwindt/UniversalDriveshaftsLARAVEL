const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  console.log(JSON.stringify(await p.evaluate(() => {
    const el = document.querySelector('.dsb-hero-logo'), img = el.querySelector('img'); const out = [];
    const walk = (rules, href, media) => { for (const r of rules) { if (r.cssRules) { if (r.media && !matchMedia(r.media.mediaText).matches) continue; walk(r.cssRules, href, r.media ? r.media.mediaText : media); continue; } if (!r.selectorText) continue;
      for (const [t, name] of [[el, 'logo'], [img, 'img']]) { try { if (t.matches(r.selectorText) && (r.style.width || r.style.minWidth || r.style.flex || r.style.maxWidth)) out.push({ name, href: (href || 'inline').slice(-22), media, sel: r.selectorText.slice(0, 60), width: r.style.width, minWidth: r.style.minWidth, maxWidth: r.style.maxWidth, imp: r.style.getPropertyPriority('width') }); } catch (e) {} } } };
    for (const s of document.styleSheets) { try { walk(s.cssRules, s.href, null); } catch (e) { out.push({ err: (s.href || 'inline').slice(-22) }); } }
    return { inlineStyle: el.getAttribute('style'), imgAttrs: img.outerHTML.slice(0, 120), computed: getComputedStyle(el).width, rules: out };
  }), null, 1));
  await b.close(); })();
