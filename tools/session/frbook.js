const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1440,height:1000}});
  const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
  await p.fill('#l-email','freight-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
  await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded'}), p.click('form.authform button[type=submit], form.authform .btn')]);
  await p.goto('https://dev.udshafts.com/admin/orders/26',{waitUntil:'networkidle'});
  await p.click('[data-modal="freight"]'); await p.waitForTimeout(500);
  await p.click('[data-fr-get]'); await p.waitForTimeout(4000);
  await p.check('[data-fr-confirm]');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}), p.click('[data-fr-book]')]);
  const flash=await p.$eval('.flash, .toast, [class*=flash]', el=>el.textContent.trim()).catch(()=>'(no flash)');
  console.log('flash:', flash.replace(/\s+/g,' ').slice(0,220));
  const card=await p.$('text=Freight (LTL)'); await card.scrollIntoViewIfNeeded(); await p.screenshot({path:'fr-d-booked.png'});
  console.log('errors:', errs.length?errs:'none');
  await b.close();
})();
