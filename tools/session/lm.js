const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch(); const p=await (await b.newContext({viewport:{width:1440,height:900}})).newPage();
 const errs=[]; p.on('pageerror',e=>errs.push(e.message)); p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
 await p.goto('https://dev.udshafts.com/login'); await p.fill('#l-email','admin-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
 await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit], form.authform .btn')]);
 await p.goto('https://dev.udshafts.com/admin/orders/10',{waitUntil:'networkidle'}); await p.click('[data-modal="label"]'); await p.waitForTimeout(3000);
 console.log(await p.evaluate(()=>{const s=document.querySelector('.modal [data-label-sum]'); const c=document.querySelector('.modal [name=service_code]:checked'); return {sum:s&&s.outerHTML, checked:c&&c.getAttribute('data-key'), carrier:document.querySelector('.modal [data-label-carrier]').value, buyDisabled:document.querySelector('.modal [data-label-buy]').disabled}}));
 console.log('errs',errs); await b.close(); })();
