const {chromium}=require(process.env.PWC);
const fs=require('fs');
const DIR=__dirname, SHOT=DIR+'/shots/';
const BASE='https://dev.udshafts.com';
const PW=fs.readFileSync(DIR+'/../qa-pw','utf8').trim();
const pub=(process.env.PAGES||'/,/shop,/parts/5-1310XU,/search?q=1310,/catalog/driveshafts,/driveshaft-designer,/online-builder,/track-order,/contact,/wholesale,/return-policy,/terms,/about,/services,/login,/register,/cart,/checkout').split(',');
const priv=(process.env.PRIV||'/account,/account/orders,/account/addresses,/account/business,/account/settings,/wishlist').split(',');
const vps={m:{viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2},d:{viewport:{width:1440,height:900}}};
const audit=()=>{
  const W=innerWidth, out={url:location.pathname+location.search,title:document.title,sw:document.documentElement.scrollWidth,iw:W,h:document.documentElement.scrollHeight};
  const vis=e=>{const r=e.getBoundingClientRect();const s=getComputedStyle(e);return r.width>0&&r.height>0&&s.visibility!='hidden'&&s.display!='none'&&s.opacity!='0'};
  const desc=e=>(e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.className&&typeof e.className=='string'?'.'+e.className.trim().split(/\s+/).slice(0,3).join('.'):''));
  out.wide=[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return vis(e)&&r.right>W+1&&r.width<4000}).slice(0,8).map(e=>desc(e)+' r='+Math.round(e.getBoundingClientRect().right));
  out.brokenImg=[...document.images].filter(i=>i.complete&&i.naturalWidth==0&&vis(i)).map(i=>i.currentSrc||i.src).slice(0,10);
  out.small=[...document.querySelectorAll('a,button,input,select,textarea,[role=button]')].filter(vis).filter(e=>{const r=e.getBoundingClientRect();return (r.height<32||r.width<32)&&r.top<document.documentElement.scrollHeight}).filter(e=>!(e.tagName=='A'&&getComputedStyle(e).display=='inline')).slice(0,12).map(e=>{const r=e.getBoundingClientRect();return desc(e)+' "'+(e.innerText||e.value||e.getAttribute('aria-label')||'').trim().slice(0,25)+'" '+Math.round(r.width)+'x'+Math.round(r.height)});
  out.unlabeled=[...document.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]),select,textarea')].filter(vis).filter(e=>!(e.labels&&e.labels.length)&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>desc(e)+'[name='+e.name+'] ph='+(e.placeholder||'')).slice(0,10);
  out.edge=[...document.querySelectorAll('h1,h2,h3,p,button,a.btn,input,label,li')].filter(vis).filter(e=>{const r=e.getBoundingClientRect();return W<500&&r.width<W-2&&(r.left<4||W-r.right<4)&&r.width>40}).slice(0,6).map(e=>desc(e)+' L'+Math.round(e.getBoundingClientRect().left)+' R'+Math.round(W-e.getBoundingClientRect().right));
  out.fixed=[...document.querySelectorAll('body *')].filter(e=>{const s=getComputedStyle(e);return (s.position=='fixed'||s.position=='sticky')&&vis(e)}).map(e=>{const r=e.getBoundingClientRect();return desc(e)+' '+Math.round(r.left)+','+Math.round(r.top)+' '+Math.round(r.width)+'x'+Math.round(r.height)}).slice(0,12);
  out.clipped=[...document.querySelectorAll('h1,h2,h3,h4,button,a,span,p,td,label')].filter(vis).filter(e=>{const s=getComputedStyle(e);return (s.overflow=='hidden'||s.textOverflow=='ellipsis')&&e.scrollWidth>e.clientWidth+2&&e.innerText.trim().length>3}).slice(0,8).map(e=>desc(e)+' "'+e.innerText.trim().slice(0,40)+'"');
  out.h1=[...document.querySelectorAll('h1')].map(e=>e.innerText.trim().slice(0,60));
  return out;
};
(async()=>{
  const b=await chromium.launch({executablePath:process.env.BX});
  const results=[];
  for(const [vk,vo] of Object.entries(vps)){
    if(process.env.VP&&process.env.VP!=vk) continue;
    const ctx=await b.newContext({...vo,ignoreHTTPSErrors:true});
    const page=await ctx.newPage();
    let log=[];
    page.on('console',m=>{if(m.type()=='error'||m.type()=='warning')log.push(m.type()+': '+m.text().slice(0,200))});
    page.on('pageerror',e=>log.push('pageerror: '+e.message.slice(0,200)));
    page.on('requestfailed',r=>{const u=r.url();if(!/google|facebook|doubleclick|clarity|hotjar/.test(u))log.push('failed: '+u.slice(0,150)+' '+(r.failure()||{}).errorText)});
    page.on('response',r=>{if(r.status()>=400)log.push('HTTP '+r.status()+' '+r.url().slice(0,150))});
    const visit=async(p,tag)=>{
      log=[];
      let st=0;
      try{const resp=await page.goto(BASE+p,{waitUntil:'networkidle',timeout:45000});st=resp?resp.status():0}catch(e){log.push('goto: '+e.message.slice(0,100))}
      await page.waitForTimeout(800);
      // CLS
      let cls=await page.evaluate(()=>new Promise(r=>{let v=0;try{new PerformanceObserver(l=>{for(const e of l.getEntries())if(!e.hadRecentInput)v+=e.value}).observe({type:'layout-shift',buffered:true})}catch(e){};setTimeout(()=>r(v),300)}));
      const a=await page.evaluate(audit);
      const name=vk+'_'+(tag||p.replace(/[^a-z0-9]+/gi,'_').replace(/^_|_$/g,'')||'home');
      await page.screenshot({path:SHOT+name+'.png',fullPage:true}).catch(()=>{});
      await page.screenshot({path:SHOT+name+'_fold.png'}).catch(()=>{});
      results.push({vp:vk,status:st,cls:+cls.toFixed(3),...a,log:[...new Set(log)].slice(0,15),shot:name});
      console.error(vk,p,st,'sw',a.sw,'/',a.iw);
    };
    for(const p of pub) await visit(p);
    if(!process.env.NOPRIV){
      await page.goto(BASE+'/login',{waitUntil:'networkidle'});
      await page.fill('#l-email','qa-shot-temp@example.com'); await page.fill('#l-pw',PW);
      await Promise.all([page.waitForNavigation({timeout:30000}).catch(()=>{}),page.click('form.authform button[type=submit]')]);
      console.error('after login',page.url());
      for(const p of priv) await visit(p);
    }
    await ctx.close();
  }
  fs.writeFileSync(DIR+'/results'+(process.env.OUT||'')+'.json',JSON.stringify(results,null,1));
  await b.close();
})();
