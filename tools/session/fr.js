const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  for (const [w,h,tag] of [[1440,1000,'d'],[390,844,'m']]) {
  const ctx=await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:1});
  const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
  await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
  await p.fill('#l-email','freight-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
  await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}), p.click('form.authform button[type=submit], form.authform .btn')]);
  await p.goto('https://dev.udshafts.com/admin/orders/26',{waitUntil:'networkidle'});
  const card=await p.$('text=Freight (LTL)'); console.log(tag,'card:',!!card);
  if (card) { await card.scrollIntoViewIfNeeded(); await p.screenshot({path:`fr-${tag}-card.png`}); }
  await p.click('[data-modal="freight"]'); await p.waitForTimeout(500);
  await p.click('[data-fr-get]'); await p.waitForTimeout(4000);
  await p.screenshot({path:`fr-${tag}-quotes.png`});
  await p.check('[data-fr-confirm]'); await p.waitForTimeout(300);
  console.log(tag,'book enabled after confirm:', await p.isEnabled('[data-fr-book]'), '| sum:', (await p.textContent('[data-fr-sum]')).trim());
  console.log(tag,'class:', (await p.textContent('[data-fr-class]')).trim());
  await p.fill('[name=weight_lb]','400'); await p.waitForTimeout(300);
  console.log(tag,'book enabled after load change:', await p.isEnabled('[data-fr-book]'), '| class:', (await p.textContent('[data-fr-class]')).trim());
  await p.screenshot({path:`fr-${tag}-stale.png`});
  console.log(tag,'errors:', errs.length?errs:'none');
  await ctx.close(); }
  await b.close();
})();
