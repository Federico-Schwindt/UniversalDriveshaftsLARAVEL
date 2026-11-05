const {chromium}=require(process.env.PWC);
const fs=require('fs');
const DIR=__dirname, SHOT=DIR+'/shots/';
const BASE='https://dev.udshafts.com';
const vk=process.env.VP||'m';
const vo={m:{viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2},d:{viewport:{width:1440,height:900}}}[vk];
const out=[];const L=(...a)=>{const s=a.map(x=>typeof x=='string'?x:JSON.stringify(x)).join(' ');out.push(s);console.log(s)};
(async()=>{
  const b=await chromium.launch({executablePath:process.env.BX});
  const ctx=await b.newContext({...vo,ignoreHTTPSErrors:true});
  const page=await ctx.newPage();
  page.on('console',m=>{if(m.type()=='error')L('CONSOLE',page.url(),m.text().slice(0,200))});
  page.on('pageerror',e=>L('PAGEERR',page.url(),e.message.slice(0,200)));
  page.on('response',r=>{if(r.status()>=400)L('HTTP',r.status(),r.url().slice(0,150))});
  const shot=async n=>{await page.screenshot({path:SHOT+vk+'_'+n+'.png'});};
  const go=async p=>{await page.goto(BASE+p,{waitUntil:'networkidle',timeout:45000}).catch(e=>L('goto',e.message.slice(0,80)));await page.waitForTimeout(600)};
  // empty img on PDP
  await go('/parts/5-1310XU');
  L('emptyimgs',await page.evaluate(()=>[...document.images].filter(i=>i.naturalWidth==0).map(i=>i.outerHTML.slice(0,250))));
  // menu
  if(vk=='m'){await page.click('header button[aria-controls], header .burger, header button[aria-label*=enu]').catch(e=>L('menu click fail'));await page.waitForTimeout(700);await shot('menu');
    L('menu links',await page.evaluate(()=>[...document.querySelectorAll('nav a')].filter(a=>a.offsetParent).slice(0,40).map(a=>a.innerText.trim()+'>'+a.getAttribute('href'))));
    await page.keyboard.press('Escape');}
  // add to cart
  await go('/parts/5-1310XU');
  const t0=Date.now();const btn=await page.$('button:has-text("Add to cart")');
  if(btn){await btn.scrollIntoViewIfNeeded();await shot('pdp_atc_before');await btn.click();await page.waitForTimeout(1500);await shot('pdp_atc_after');}
  else L('no ATC button');
  // focus style check
  await page.keyboard.press('Tab');await page.keyboard.press('Tab');await page.keyboard.press('Tab');
  L('focus',await page.evaluate(()=>{const e=document.activeElement;const s=getComputedStyle(e);return e.tagName+' '+e.className+' outline='+s.outlineStyle+' '+s.outlineWidth+' '+s.outlineColor+' shadow='+s.boxShadow}));
  await shot('focus');
  await page.goto(BASE+'/cart',{waitUntil:'load'});await page.waitForTimeout(2500);await shot('cart_full');await page.screenshot({path:SHOT+vk+'_cart_full_page.png',fullPage:true});
  await go('/checkout');L('checkout url',page.url());await shot('checkout');await page.screenshot({path:SHOT+vk+'_checkout_page.png',fullPage:true});
  L('checkout unlabeled',await page.evaluate(()=>[...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(e=>e.offsetParent&&!(e.labels&&e.labels.length)&&!e.getAttribute('aria-label')).map(e=>e.name+'|'+e.id+'|'+e.placeholder)));
  // finder
  await go('/catalog/driveshafts');
  L('finder structure',await page.evaluate(()=>{const m=document.querySelector('main')||document.body;return [...m.querySelectorAll('a,button,select')].filter(e=>e.offsetParent).slice(0,30).map(e=>e.tagName+'.'+e.className+' '+(e.innerText||'').trim().slice(0,20)+' '+(e.getAttribute('href')||''))}));
  for(let step=0;step<5;step++){
    const before=page.url();
    const clicked=await page.evaluate(()=>{const c=[...document.querySelectorAll('main button.fdr-tile, main a.fdr-tile, main .fdr-result a')].filter(e=>e.offsetParent);if(!c.length)return null;const e=c[Math.min(2,c.length-1)];const t=e.innerText.trim().slice(0,30);e.click();return t});
    L('finder step',step,clicked);
    if(!clicked)break;
    await page.waitForLoadState('networkidle').catch(()=>{});await page.waitForTimeout(1200);
    await shot('finder_'+step);
    L('  url',page.url(),'sw',await page.evaluate(()=>document.documentElement.scrollWidth));
  }
  await page.screenshot({path:SHOT+vk+'_finder_final_page.png',fullPage:true});
  // language
  for(const [p,lang] of [['/','es'],['/shop','fr'],['/parts/5-1310XU','es'],['/cart','fr'],['/contact','es']]){
    await go(p);
    const a=await page.$(`a[href*="lang=${lang}"]:visible`);
    if(!a){L('no lang link',lang);continue}
    await a.click({force:true});await page.waitForLoadState('networkidle').catch(()=>{});await page.waitForTimeout(800);
    L('lang',lang,p,'->',page.url(),'htmllang',await page.evaluate(()=>document.documentElement.lang));
    await shot('lang_'+lang+'_'+(p.replace(/\W+/g,'_')||'home'));
    await page.screenshot({path:SHOT+vk+'_lang_'+lang+'_'+(p.replace(/\W+/g,'_'))+'_page.png',fullPage:true});
    // English leftovers
    L('  en words',await page.evaluate(()=>{const re=/\b(the|and|your|with|shipping|cart|order|add|search|price|stock|ships|free|view|contact|subscribe|parts|wholesale|account|sign in)\b/i;const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const r=new Set();let n;while(n=w.nextNode()){const t=n.textContent.trim();if(t.length>3&&re.test(t)&&n.parentElement.offsetParent&&!n.parentElement.closest('script,style,code,.notranslate,[translate=no]'))r.add(t.slice(0,70))}return [...r].slice(0,25)}));
  }
  // back to english
  await go('/?lang=en');
  fs.writeFileSync(DIR+'/flow_'+vk+'.txt',out.join('\n'));
  await b.close();
})();
