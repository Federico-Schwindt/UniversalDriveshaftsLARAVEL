const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({ headless: true });
  for (const w of [1280, 390]) {
    const p = await b.newPage({ viewport: { width: w, height: 800 } });
    await p.goto('file://' + process.argv[2]);
    await p.click('#newbtn'); await p.waitForTimeout(120);
    const r = await p.evaluate(() => {
      const out = [];
      document.querySelectorAll('*').forEach(el => {
        const r = el.getBoundingClientRect();
        if (r.right > window.innerWidth + 0.5) out.push(el.tagName + '.' + (el.className || el.id) + ' right=' + Math.round(r.right) + ' w=' + Math.round(r.width));
      });
      const pop = document.querySelector('.adm-menu__pop'), a = document.getElementById('neworder'), top = document.querySelector('.adm-top');
      const ar = a.getBoundingClientRect(); const hit = document.elementFromPoint(ar.left + ar.width/2, ar.top + ar.height/2);
      return { offenders: out.slice(0, 6), popBottom: Math.round(pop.getBoundingClientRect().bottom), barBottom: Math.round(top.getBoundingClientRect().bottom),
        clickable: hit ? hit.id === 'neworder' : false, sideways: document.documentElement.scrollWidth > window.innerWidth };
    });
    console.log('w=' + w, JSON.stringify(r, null, 1));
    await p.close();
  }
  await b.close();
})();
