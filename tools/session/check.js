const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  for (const w of [1280, 390]) {
    const p = await b.newPage({ viewport: { width: w, height: 800 } });
    const errs = []; p.on('pageerror', e => errs.push(String(e)));
    await p.goto('file://' + process.argv[2]);
    await p.click('#newbtn'); await p.waitForTimeout(150);
    const r = await p.evaluate(() => {
      const pop = document.querySelector('.adm-menu__pop'), a = document.getElementById('neworder');
      const top = document.querySelector('.adm-top'), ar = a.getBoundingClientRect();
      const hit = document.elementFromPoint(ar.left + ar.width / 2, ar.top + ar.height / 2);
      return { overflow: getComputedStyle(top).overflow,
        popBottom: Math.round(pop.getBoundingClientRect().bottom), barBottom: Math.round(top.getBoundingClientRect().bottom),
        clickableNewOrder: hit ? (hit.id === 'neworder') : false,
        sidewaysScroll: document.documentElement.scrollWidth > window.innerWidth,
        scrollW: document.documentElement.scrollWidth, winW: window.innerWidth };
    });
    console.log('w=' + w, JSON.stringify(r)); if (errs.length) console.log('  JS ERRORS:', errs);
    await p.screenshot({ path: process.argv[3].replace('.png', '-' + w + '.png') });
    await p.close();
  }
  await b.close();
})();
