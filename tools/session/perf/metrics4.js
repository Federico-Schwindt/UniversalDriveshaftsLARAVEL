const { chromium } = require(process.env.PWC); const fs = require('fs');
const PAGES = ['/', '/parts/5-1310XU', '/shop/driveshaft-components/u-joints', '/driveshafts/honda/pilot', '/series/1310', '/guides/how-to-measure-a-driveshaft', '/services', '/driveshafts/ford/f350-super-duty'];
(async () => {
  const b = await chromium.launch({ headless: true }); const out = [];
  for (const mobile of [false, true]) for (const path of PAGES) {
    const ctx = await b.newContext({ viewport: mobile ? { width: 390, height: 844 } : { width: 1400, height: 900 }, deviceScaleFactor: mobile ? 2 : 1, userAgent: mobile ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' : undefined });
    const p = await ctx.newPage(); const reqs = []; const errors = [];
    p.on('response', async r => { try { const h = r.headers(); reqs.push({ url: r.url(), status: r.status(), type: r.request().resourceType(), len: +(h['content-length'] || 0), enc: h['content-encoding'] || '', cache: h['cache-control'] || '' }); } catch (e) {} });
    p.on('pageerror', e => errors.push('JS: ' + e.message)); p.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 120)); });
    await p.addInitScript(() => { window.__lcp = 0; window.__cls = 0; try { new PerformanceObserver(l => { for (const e of l.getEntries()) window.__lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true }); new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {} });
    if (!mobile) await p.coverage.startCSSCoverage();
    const t0 = Date.now();
    try { await p.goto('https://dev.udshafts.com' + path, { waitUntil: 'networkidle', timeout: 45000 }); } catch (e) { errors.push('nav: ' + e.message.slice(0, 80)); }
    await p.waitForTimeout(600);
    const nav = await p.evaluate(() => { const n = performance.getEntriesByType('navigation')[0] || {}; const imgs = [...document.images]; return { ttfb: Math.round(n.responseStart || 0), dcl: Math.round(n.domContentLoadedEventEnd || 0), load: Math.round(n.loadEventEnd || 0), lcp: Math.round(window.__lcp || 0), cls: +(window.__cls || 0).toFixed(3), imgs: imgs.length, imgsNoDim: imgs.filter(i => !i.getAttribute('width') || !i.getAttribute('height')).length, imgsNoLazy: imgs.filter(i => i.loading !== 'lazy' && i.getBoundingClientRect().top > innerHeight).length, imgsOversized: imgs.filter(i => i.naturalWidth > 2 * i.getBoundingClientRect().width + 100 && i.getBoundingClientRect().width > 0).map(i => i.currentSrc.split('/').pop() + ' ' + i.naturalWidth + '→' + Math.round(i.getBoundingClientRect().width)).slice(0, 5), h1: document.querySelectorAll('h1').length, title: document.title.length, domNodes: document.getElementsByTagName('*').length }; });
    let css = null; if (!mobile) { const cov = await p.coverage.stopCSSCoverage(); css = cov.filter(c => /\.css/.test(c.url)).map(c => { const used = c.ranges.reduce((a, r) => a + r.end - r.start, 0); return { file: c.url.split('/').pop().split('?')[0], total: c.text.length, used }; }); }
    const bytes = reqs.reduce((a, r) => a + r.len, 0); const byType = {}; for (const r of reqs) byType[r.type] = (byType[r.type] || 0) + r.len;
    const big = reqs.filter(r => r.len > 150000).sort((a, b) => b.len - a.len).slice(0, 5).map(r => r.url.replace('https://dev.udshafts.com', '').split('?')[0] + ' ' + Math.round(r.len / 1024) + 'KB');
    const bad = reqs.filter(r => r.status >= 400).map(r => r.status + ' ' + r.url.replace('https://dev.udshafts.com', '').slice(0, 80));
    const uncompressed = reqs.filter(r => /document|stylesheet|script|xhr|fetch/.test(r.type) && !r.enc && r.len > 2000).map(r => r.url.replace('https://dev.udshafts.com', '').slice(0, 60));
    out.push({ path, mobile, ...nav, requests: reqs.length, kb: Math.round(bytes / 1024), byType: Object.fromEntries(Object.entries(byType).map(([k, v]) => [k, Math.round(v / 1024)])), big, bad, uncompressed, errors: [...new Set(errors)].slice(0, 4), css, wall: Date.now() - t0 });
    process.stdout.write((mobile ? 'M ' : 'D ') + path + ' ');
    await ctx.close();
  }
  fs.writeFileSync(process.env.SP + '/perf/metrics-seo.json', JSON.stringify(out, null, 1)); console.log('\ndone');
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
