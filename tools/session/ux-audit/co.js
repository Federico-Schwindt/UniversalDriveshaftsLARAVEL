const {chromium}=require(process.env.PWC);const SHOT=__dirname+'/shots/';const BASE='https://dev.udshafts.com';
const L=(...a)=>console.log(a.map(x=>typeof x=='string'?x:JSON.stringify(x)).join(' '));
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
const ctx=await b.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2});const page=await ctx.newPage();
page.on('console',m=>{if(m.type()=='error')L('CONSOLE',m.text().slice(0,200))});
const go=async p=>{await page.goto(BASE+p,{waitUntil:'load'});await page.waitForTimeout(2500)};
// overlap checker: for floating elements vs interactive elements at initial scroll and after scrolling to each
const overlap=async(name)=>L(name,await page.evaluate(()=>{const fl=[...document.querySelectorAll('.floaty a,.floaty button,button.ua-launch')].filter(e=>e.offsetParent).map(e=>e.getBoundingClientRect());const hits=[];
 for(const e of document.querySelectorAll('main a,main button,main input,main select,main textarea,form a,form button,form input')){if(!e.offsetParent||e.closest('.floaty'))continue;const r=e.getBoundingClientRect();if(r.bottom<0||r.top>innerHeight)continue;for(const f of fl){if(r.left<f.right&&r.right>f.left&&r.top<f.bottom&&r.bottom>f.top){hits.push((e.innerText||e.name||e.placeholder||e.tagName).trim().slice(0,30));break}}}return hits}));
for(const p of ['/login','/register','/contact','/cart','/','/online-builder','/track-order','/wholesale']){await go(p);await overlap(p);}
await go('/parts/5-1310XU');await page.click('button:has-text("Add to cart")');await page.waitForTimeout(1500);
await go('/cart');await overlap('/cart (with item)');
await go('/checkout');
for(const t of ['Shipping address','Delivery method','Payment details','Order notes','Order summary']){const e=page.locator('text='+t).first();if(await e.count()){await e.scrollIntoViewIfNeeded();await page.evaluate(()=>scrollBy(0,-120));await page.waitForTimeout(1200);await page.screenshot({path:SHOT+'m_co_'+t.replace(/\W/g,'_')+'.png'});await overlap('checkout@'+t)}}
L('payment frames',await page.evaluate(()=>[...document.querySelectorAll('iframe')].map(f=>(f.name||'').slice(0,30)+' '+f.offsetHeight)));
L('autocomplete attrs',await page.evaluate(()=>[...document.querySelectorAll('form input')].filter(e=>e.offsetParent).map(e=>e.name+':'+(e.getAttribute('autocomplete')||'-')).join(' ')));
L('prefilled',await page.evaluate(()=>['billing_city','billing_state','billing_postcode'].map(n=>{const e=document.querySelector(`[name=${n}]`);return n+'='+(e&&e.value)})));
await b.close()})();
