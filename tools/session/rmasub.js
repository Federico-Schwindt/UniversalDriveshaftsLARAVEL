const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  let posted=null;
  await p.route('**/rma', r=>{ if(r.request().method()!=='POST') return r.continue(); posted=r.request().postData(); r.fulfill({contentType:'application/json',body:'{"success":true,"ref":"RMA-TEST-0001","type":"Exchange"}'}); });
  await p.goto('https://dev.udshafts.com/exchange-request-form?order=UDS-260910-W0GM&e=x',{waitUntil:'networkidle'});
  await p.fill('[name=order_number]','UDS-260910-W0GM'); await p.fill('[name=name]','Test Person'); await p.fill('[name=email]','t@example.com');
  await p.fill('[name=product_free]','2x 5-1310X'); await p.click('.rma2-chip:nth-child(2) span'); await p.fill('[name=want]','5-1350X');
  await p.fill('[name=details_free]','Wrong series, need 1350.'); await p.click('#rma-submit'); await p.waitForTimeout(1200);
  const q=new URLSearchParams(posted||''); console.log('POST fields:', [...q.keys()].join(','));
  console.log('product =', q.get('product')); console.log('details =', JSON.stringify(q.get('details')));
  console.log('done shown:', await p.isVisible('#rma-done'), '| ref:', await p.textContent('#rma-ref'));
  await p.screenshot({path:'ord/m-rma-done.png'});
  await p.goto('https://dev.udshafts.com/return-request-form?order=UDS-QA-ORD1&e=orders-qa%40udshafts.test',{waitUntil:'networkidle'});
  await p.screenshot({path:'ord/m-rma2.png',fullPage:true});
  console.log('errors:', errs.length?errs:'none'); await b.close();
})();
