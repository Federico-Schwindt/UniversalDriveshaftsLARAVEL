const { chromium } = require(process.env.PWC);
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1280,height:1100}});
  const p=await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto('https://dev.udshafts.com/quick-order',{waitUntil:'domcontentloaded'});
  const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]').content);
  const add=async(lines)=>p.evaluate(async([t,l])=>{const r=await fetch('/quick-order/add',{method:'POST',headers:{'X-CSRF-TOKEN':t,'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams({lines:JSON.stringify(l)})});return r.text()},[tok,lines]);
  console.log('add:', await add([{sku:'ATU5125',qty:10},{sku:'10-2-29',qty:10}]));
  await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'domcontentloaded'}); await p.waitForTimeout(4000);
  const opts=async()=>p.$$eval('#ship-select option',o=>o.map(x=>x.textContent).filter(t=>t&&!/Choose|Loading/.test(t)));
  const fill=async(street,city,state,zip,country='US')=>{
    await p.selectOption('#billing_country',country).catch(()=>{});
    await p.fill('[name=billing_address_1]',street); await p.fill('[name=billing_city]',city);
    await p.fill('[name=billing_state]',state); await p.fill('[name=billing_postcode]',zip);
    await p.locator('[name=billing_postcode]').blur(); await p.waitForTimeout(9000);
  };
  await fill('100 Example St','Tampa','FL','33602');
  console.log('freight box visible:', await p.isVisible('#freight-box'), '| ships:', await p.textContent('#ship-ships'));
  console.log('residential:', await opts());
  await p.screenshot({path:'co2-a.png',fullPage:false, clip:{x:0,y:0,width:1280,height:1100}});
  await p.check('[name=freight_delivery][value=dock]'); await p.waitForTimeout(9000);
  console.log('dock:       ', await opts());
  const box=await p.$('#freight-box'); await box.scrollIntoViewIfNeeded(); await p.screenshot({path:'co2-b.png'});
  await p.fill('[name=billing_address_1]','PO Box 123'); await p.locator('[name=billing_address_1]').blur(); await p.waitForTimeout(3000);
  console.log('po box note:', await p.textContent('#ship-note'), '| opts:', await opts());
  console.log('errors:', errs.length?errs:'none');
  await b.close();
})();
