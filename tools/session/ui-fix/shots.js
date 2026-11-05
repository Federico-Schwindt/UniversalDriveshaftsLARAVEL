const {chromium}=require(process.env.PWC);
const fs=require('fs');
const DIR=__dirname, TAG=process.argv[2]||'after', SHOT=DIR+'/'+TAG+'/';
fs.mkdirSync(SHOT,{recursive:true});
const BASE='https://dev.udshafts.com';
const PW=fs.readFileSync(DIR+'/../qa-pw','utf8').trim();
const L=(...a)=>console.log(a.map(x=>typeof x=='string'?x:JSON.stringify(x)).join(' '));
const ONLY=process.argv[3]?process.argv[3].split(','):null;
(async()=>{
  const b=await chromium.launch({executablePath:process.env.BX});
  for (const [vp,w,h] of [['m',390,844],['d',1440,900]]) {
    const ctx=await b.newContext({viewport:{width:w,height:h},isMobile:vp=='m',hasTouch:vp=='m',deviceScaleFactor:vp=='m'?2:1,ignoreHTTPSErrors:true});
    const page=await ctx.newPage();
    page.on('console',m=>{if(m.type()=='error')L(vp,'CONSOLE',m.text().slice(0,160))});
    const go=async p=>{await page.goto(BASE+p,{waitUntil:'load',timeout:45000}).catch(e=>L('goto',p,e.message.slice(0,60)));await page.waitForTimeout(1500)};
    const shot=async n=>{if(ONLY&&!ONLY.includes(n))return;await page.screenshot({path:SHOT+vp+'_'+n+'.png'});};
    const floaty=async n=>L(vp,n,'floaty',await page.evaluate(()=>{const f=document.querySelector('.floaty');const u=document.querySelector('.ua-launch');const r=e=>e&&getComputedStyle(e).display!='none'?JSON.stringify(e.getBoundingClientRect().toJSON(),['x','y','width','height']):'none';return 'floaty='+r(f)+' launch='+r(u)+' bodyPB='+getComputedStyle(document.body).paddingBottom+' overflowX='+(document.documentElement.scrollWidth>innerWidth)}));
    await go('/?lang=en'); await shot('home'); await floaty('home');
    L(vp,'home counts',await page.evaluate(()=>[document.querySelector('.n-vehicle__t small')?.textContent,document.querySelector('#nv-make option')?.textContent,document.querySelector('.umega__feat span')?.textContent]));
    await go('/shop'); await shot('shop');
    L(vp,'shop hero btn',await page.evaluate(()=>[...document.querySelectorAll('.phero__cta .btn')].map(e=>Math.round(e.getBoundingClientRect().right)+'/'+innerWidth)));
    for (const p of ['/custom-off-road-driveshafts','/premade-driveshafts','/industrial-driveshafts','/heavy-duty-driveshafts','/pto-agriculture','/services']) {
      await go(p); L(vp,p,await page.evaluate(()=>[...document.querySelectorAll('.phero__cta .btn, .phero .btn')].map(e=>Math.round(e.getBoundingClientRect().right)+'/'+innerWidth).join(' ')+' docW='+document.documentElement.scrollWidth));
    }
    await go('/contact'); await shot('contact'); await floaty('contact');
    await go('/cart'); await shot('cart'); await floaty('cart');
    await go('/login'); await shot('login'); await floaty('login');
    await go('/register'); await floaty('register');
    await go('/online-builder'); await shot('builder');
    L(vp,'dsb label',await page.evaluate(()=>{const e=document.querySelector('.dsb-hero-price-label');if(!e)return 'none';let p=e,bg;while(p&&(bg=getComputedStyle(p).backgroundColor)=='rgba(0, 0, 0, 0)')p=p.parentElement;return getComputedStyle(e).color+' on '+bg+' '+(p&&p.className)}));
    await go('/parts/5-1310XU?lang=es'); await page.evaluate(()=>document.querySelector('.shipcd')?.scrollIntoView({block:'center'})); L(vp,'es shipcd',await page.evaluate(()=>[...document.querySelectorAll('[data-shipcd]')].map(e=>e.getAttribute('data-after'))));
    await go('/parts/5-1310XU?lang=fr'); L(vp,'fr shipcd',await page.evaluate(()=>[...document.querySelectorAll('[data-shipcd]')].map(e=>e.getAttribute('data-after'))));
    await go('/parts/5-1310XU?lang=en'); L(vp,'en shipcd',await page.evaluate(()=>[...document.querySelectorAll('[data-shipcd]')].map(e=>e.getAttribute('data-after'))));
    await go('/?lang=en');
    if (vp=='m') {
      await page.click('#uburger'); await page.waitForTimeout(600); await shot('drawer');
      L(vp,'drawer',await page.evaluate(()=>[...document.querySelectorAll('.udrawer summary')].map(s=>s.textContent.trim()).join('|')+' lang h='+document.querySelector('.langsw--drawer a')?.getBoundingClientRect().height));
      await page.evaluate(()=>document.querySelector('.udrawer__panel').scrollTop=9999); await page.waitForTimeout(300); await shot('drawer_bottom');
    }
    // sign in
    await go('/login');
    await page.fill('#l-email','qa-shot-temp@example.com');await page.fill('#l-pw',PW);
    await Promise.all([page.waitForNavigation({timeout:30000}).catch(()=>{}),page.click('form.authform button[type=submit]')]);
    await go('/account'); await shot('account_in'); await floaty('account_in');
    await go('/account/settings'); await shot('settings_in');
    await go('/shop'); await page.evaluate(()=>scrollTo(0,600)); await page.waitForTimeout(300); await shot('shop_in'); await floaty('shop_in');
    if (vp=='m') { const t=await page.$('.floaty__toggle'); if(t){await t.click();await page.waitForTimeout(400);await shot('contact_menu_open');L(vp,'menu aria',await t.getAttribute('aria-expanded'));} }
    await ctx.close();
  }
  await b.close();
})();
