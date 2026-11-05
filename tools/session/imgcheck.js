const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true }); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const failed = []; p.on('response', r => { if (r.request().resourceType() === 'image' && r.status() >= 400) failed.push(r.status() + ' ' + r.url().slice(-70)); });
  await p.goto('https://dev.udshafts.com/', { waitUntil: 'networkidle' });
  await p.evaluate(async () => { window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 1500)); });
  const info = await p.evaluate(() => {
    const q = s => document.querySelector(s);
    const band = q('.n-band__media img'), cs = getComputedStyle(q('.n-band__media'));
    return { bandSrc: band.currentSrc.slice(-40), bandNatural: band.naturalWidth + 'x' + band.naturalHeight, bandComplete: band.complete, mediaZ: cs.zIndex, mediaPos: cs.position, bandRect: JSON.stringify(band.getBoundingClientRect()).slice(0, 80),
      hero: q('.n-hero__part img').naturalWidth, blankCats: [...document.querySelectorAll('.n-cat img')].filter(i => !i.naturalWidth).map(i => i.src.slice(-45)) };
  });
  console.log(JSON.stringify(info, null, 1)); console.log('failed image responses:', failed.length, failed.slice(0, 5));
  await b.close();
})().catch(e => { console.error('FAILED', e.message.slice(0, 200)); process.exit(1); });
