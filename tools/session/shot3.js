const { chromium } = require(process.env.PWC);
(async () => {
  const b = await chromium.launch({headless:true});
  const p = await b.newPage({viewport:{width:390,height:844}});
  await p.goto('https://dev.udshafts.com/fr/shop/driveshaft-components/u-joints',{waitUntil:'networkidle'}).catch(()=>{});
  if ((await p.title()).includes('404') || !(await p.$('.ptbl'))) await p.goto('https://dev.udshafts.com/shop/driveshaft-components/u-joints?lang=fr',{waitUntil:'networkidle'});
  const row = await p.$('.ptbl tbody tr'); const bx = await row.boundingBox();
  await row.scrollIntoViewIfNeeded(); await row.screenshot({path:'cat_m.png'});
  console.log(p.url(), await p.evaluate(()=>document.documentElement.lang));
  await b.close();
})();
