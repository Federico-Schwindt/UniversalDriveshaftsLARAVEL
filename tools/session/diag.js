const { chromium } = require(process.env.PWC);
(async () => { const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('https://dev.udshafts.com/online-builder', { waitUntil: 'networkidle' });
  console.log(JSON.stringify(await p.evaluate(() => { const g = s => { const e = document.querySelector(s), c = getComputedStyle(e); return { w: e.getBoundingClientRect().width, h: e.getBoundingClientRect().height, src: c.width }; };
    return { logo: g('.dsb-hero-logo'), img: g('.dsb-hero-logo img'), title: getComputedStyle(document.querySelector('.dsb-hero-title')).fontSize, cssHref: document.querySelector('link[href*="dsb.css"]').href.slice(-20) }; })));
  await b.close(); })();
