const {chromium}=require(process.env.PWC);
const fs=require('fs');
const DIR=__dirname, SHOT=DIR+'/shots/';
const BASE='https://dev.udshafts.com';
const PW=fs.readFileSync(DIR+'/../qa-pw','utf8').trim();
const L=(...a)=>console.log(a.map(x=>typeof x=='string'?x:JSON.stringify(x)).join(' '));
(async()=>{
  const b=await chromium.launch({executablePath:process.env.BX});
  const ctx=await b.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2,ignoreHTTPSErrors:true});
  const page=await ctx.newPage();
  page.on('response',r=>{if(r.status()>=400)L('HTTP',r.status(),r.url().slice(0,150))});
  page.on('console',m=>{if(m.type()=='error')L('CONSOLE',m.text().slice(0,200))});
  const go=async p=>{await page.goto(BASE+p,{waitUntil:'load',timeout:45000}).catch(e=>L('goto',p,e.message.slice(0,60)));await page.waitForTimeout(2500)};
  const el=async(sel,n)=>{const e=await page.$(sel);if(!e){L('nosel',sel);return}await e.scrollIntoViewIfNeeded();await page.waitForTimeout(300);await e.screenshot({path:SHOT+'m_el_'+n+'.png'}).catch(x=>L('elshot fail',n))};
  // timing of cart
  let t=Date.now();await page.goto(BASE+'/cart',{waitUntil:'networkidle',timeout:60000}).catch(e=>L('cart networkidle timeout'));L('cart networkidle ms',Date.now()-t);
  // pending requests on cart?
  // shop overflowing btn
  await go('/shop');
  L('shop btn',await page.evaluate(()=>{const e=document.querySelector('a.btn.btn-ghost.btn-lg');const r=e.getBoundingClientRect();return e.outerHTML.slice(0,200)+' '+JSON.stringify(r)+' parent '+e.parentElement.className}));
  await el('a.btn.btn-ghost.btn-lg','shop_btn_parent');
  const pe=await page.$('a.btn.btn-ghost.btn-lg');await pe.evaluate(e=>e.parentElement.style.outline='2px solid red');await pe.scrollIntoViewIfNeeded();await page.screenshot({path:SHOT+'m_shop_btn.png'});
  // PDP broken img + spec table
  await go('/parts/5-1310XU');
  L('pdp imgs src-empty',await page.evaluate(()=>[...document.images].filter(i=>!i.getAttribute('src')||i.src==location.href).map(i=>i.outerHTML.slice(0,200))));
  L('pdp wish',await page.evaluate(()=>{const e=document.querySelector('#pdp-wish');return e&&e.outerHTML.slice(0,300)}));
  // checkout section
  await go('/checkout');
  L('checkout labels',await page.evaluate(()=>[...document.querySelectorAll('label')].slice(0,12).map(l=>l.outerHTML.slice(0,140))));
  L('stripe frames',await page.evaluate(()=>[...document.querySelectorAll('iframe')].map(f=>f.src.slice(0,80)+' '+f.offsetHeight)));
  const sh=await page.$('text=Shipping address');if(sh){await sh.scrollIntoViewIfNeeded();await page.evaluate(()=>scrollBy(0,-150));await page.waitForTimeout(400);await page.screenshot({path:SHOT+'m_checkout_shipsec.png'})}
  const pd=await page.$('text=Payment details');if(pd){await pd.scrollIntoViewIfNeeded();await page.waitForTimeout(2500);await page.screenshot({path:SHOT+'m_checkout_payment.png'})}
  const os=await page.$('text=Order summary');if(os){await os.scrollIntoViewIfNeeded();await page.waitForTimeout(400);await page.screenshot({path:SHOT+'m_checkout_summary.png'})}
  // track-order, contact, wholesale, login fold shots already. Login now
  await go('/login');
  await page.fill('#l-email','qa-shot-temp@example.com');await page.fill('#l-pw',PW);
  await Promise.all([page.waitForNavigation({timeout:30000}).catch(()=>{}),page.click('form.authform button[type=submit]')]);
  await go('/account/settings');await page.screenshot({path:SHOT+'m_settings_page.png',fullPage:true});
  await go('/account/business');await page.screenshot({path:SHOT+'m_business_page.png',fullPage:true});
  await go('/account/addresses');await page.screenshot({path:SHOT+'m_addresses_page.png',fullPage:true});
  // chat
  await go('/account');
  await page.click('button.ua-launch');await page.waitForTimeout(1500);
  await page.screenshot({path:SHOT+'m_chat_open.png'});
  L('chat panel',await page.evaluate(()=>{const p=document.querySelector('.ua-panel,[class*=ua-win],[class*=ua-dialog],[role=dialog]');if(!p)return 'nopanel';const r=p.getBoundingClientRect();return p.className+' '+JSON.stringify(r)}));
  L('chat inputs',await page.evaluate(()=>[...document.querySelectorAll('[class*=ua] textarea,[class*=ua] input')].map(e=>e.tagName+' '+e.className+' fs='+getComputedStyle(e).fontSize+' lab='+(e.getAttribute('aria-label')||''))));
  // send 1 message
  const ta=await page.$('[class*=ua] textarea, [class*=ua] input[type=text]');
  if(ta){await ta.tap().catch(()=>ta.click());await ta.fill('Do you have a 1310 u-joint in stock?');await page.keyboard.press('Enter');await page.waitForTimeout(20000);await page.screenshot({path:SHOT+'m_chat_reply.png'});}
  const hb=await page.$('button.ua-iconbtn[aria-label*="past"], button.ua-iconbtn[title*="past"]');
  if(hb){await hb.click();await page.waitForTimeout(1500);await page.screenshot({path:SHOT+'m_chat_history.png'})} else L('no history btn');
  await b.close();
})();
