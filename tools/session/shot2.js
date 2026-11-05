const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({headless:true});
  for (const [n,w] of [['buy_m',390],['buy_d',1440]]) {
    const p = await b.newPage({viewport:{width:w,height:900}});
    await p.goto('https://dev.udshafts.com/parts/5-103',{waitUntil:'networkidle'});
    const el = await p.$('.pdp__cart'); const box = await el.boundingBox();
    const wish = await (await p.$('#pdp-wish')).boundingBox();
    console.log(n, JSON.stringify(box), JSON.stringify(wish));
    await p.screenshot({path:n+'.png', clip:{x:0,y:Math.max(0,box.y-120),width:w,height:box.height+220}});
    await p.close();
  }
  await b.close();
})();
