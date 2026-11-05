const { chromium } = require(process.env.PWC);
(async()=>{ const b=await chromium.launch({headless:true}); const ctx=await b.newContext({viewport:{width:1440,height:1000}}); const p=await ctx.newPage();
 const errs=[]; p.on('pageerror',e=>errs.push('pageerror: '+e.message)); p.on('console',m=>{if(m.type()==='error')errs.push('console: '+m.text())});
 p.on('response',r=>{ if(r.url().includes('/freight')) errs.push('net '+r.request().method()+' '+r.status()+' '+r.url().replace('https://dev.udshafts.com','')); });
 await p.goto('https://dev.udshafts.com/login',{waitUntil:'domcontentloaded'});
 await p.fill('#l-email','freight-qa@udshafts.test'); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD);
 await Promise.all([p.waitForNavigation(), p.click('form.authform button[type=submit], form.authform .btn')]);
 await p.goto('https://dev.udshafts.com/admin/orders/27',{waitUntil:'domcontentloaded'}); await p.waitForTimeout(1500);
 const btn=await p.$('[data-modal="freight"]'); console.log('Book freight button:', !!btn);
 if(!btn){ await p.screenshot({path:'fb-0.png',fullPage:true}); console.log(errs); await b.close(); return; }
 await btn.click(); await p.waitForTimeout(1200); await p.screenshot({path:'fb-1.png'});
 const getq=await p.$('.modal [data-freight-quote], .modal [data-freight-get], .modal button:has-text("Get"), .modal button:has-text("quote")');
 console.log('get-quotes control:', !!getq, getq? await getq.textContent():'');
 if(getq){ await getq.click(); await p.waitForTimeout(9000); }
 await p.screenshot({path:'fb-2.png'});
 const radios=await p.$$('.modal input[type=radio][name=quote_id]'); console.log('quote radios:', radios.length);
 if(radios.length){ await radios[0].check(); }
 const boxes=await p.$$('.modal input[type=checkbox]'); for (const bx of boxes){ const t=await bx.evaluate(e=>e.closest('label')?.innerText||''); if(/I confirm/i.test(t)) await bx.check(); }
 const book=await p.$('.modal [data-freight-book], .modal button:has-text("Book")'); console.log('book button disabled:', book? await book.isDisabled():'none');
 console.log('book disabled after confirm:', book? await book.isDisabled():'none');
 if(book && !(await book.isDisabled())){ await Promise.all([p.waitForLoadState('domcontentloaded').catch(()=>{}), book.click()]); await p.waitForTimeout(5000); }
 const flash=await p.evaluate(()=>[...document.querySelectorAll('.flash,.alert,[class*=flash]')].map(e=>e.innerText.trim()).join(' | '));
 console.log('flash after book:', flash.slice(0,400));
 await p.screenshot({path:'fb-3.png',fullPage:true});
 console.log(errs.join('\n')||'no errors'); await b.close(); })();
