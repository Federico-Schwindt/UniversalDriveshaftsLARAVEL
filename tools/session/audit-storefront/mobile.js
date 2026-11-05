const {chromium}=require(process.env.PWC);
const S=process.env.S;
const pages=[['home','/'],['group','/shop/driveshaft-components'],['category','/shop/driveshaft-components/u-joints'],['product','/parts/5-153X'],['finder','/catalog/driveshafts'],['vehiclepart','/catalog/driveshafts/part/U1291-206'],['cart','/cart'],['checkout','/checkout'],['search','/search?q=1310'],['notfound','/this-does-not-exist'],['vehicle-make','/driveshafts/ford'],['series','/series/1310']];
const vps=[['mobile',375,812,true],['desktop',1440,900,false]];
(async()=>{
 const b=await chromium.launch({args:['--no-sandbox']});
 const out=[];
 for(const [vn,w,h,mob] of vps){
  const ctx=await b.newContext({viewport:{width:w,height:h},isMobile:mob,hasTouch:mob,deviceScaleFactor:mob?2:1,userAgent:mob?'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 uds-audit':'Mozilla/5.0 uds-audit'});
  for(const [name,path] of pages){
   const pg=await ctx.newPage(); const cons=[],failed=[],reqs=[]; let bytes=0;
   pg.on('console',m=>{if(['error','warning'].includes(m.type()))cons.push(m.type()+': '+m.text().slice(0,160))});
   pg.on('pageerror',e=>cons.push('pageerror: '+String(e).slice(0,160)));
   pg.on('requestfailed',r=>failed.push('FAILED '+r.url().slice(0,120)+' '+(r.failure()||{}).errorText));
   pg.on('response',async r=>{reqs.push(r.url());const st=r.status();if(st>=400)failed.push(st+' '+r.url().slice(0,140));try{const l=r.headers()['content-length'];if(l)bytes+=+l;else{const bb=await r.body().catch(()=>null);if(bb)bytes+=bb.length}}catch(e){}});
   let status=0;
   try{const resp=await pg.goto('https://dev.udshafts.com'+path,{waitUntil:'networkidle',timeout:60000});status=resp?resp.status():0;}catch(e){cons.push('nav: '+e.message.slice(0,100))}
   await pg.waitForTimeout(800);
   const m=await pg.evaluate(()=>{
     const de=document.documentElement; const vw=de.clientWidth;
     const over=de.scrollWidth>vw;
     const wide=[]; if(over){for(const el of document.querySelectorAll('body *')){const r=el.getBoundingClientRect();if(r.right>vw+1&&r.width>0&&getComputedStyle(el).position!=='fixed'){wide.push(el.tagName.toLowerCase()+(el.id?'#'+el.id:'')+(el.className&&typeof el.className==='string'?'.'+el.className.trim().split(/\s+/).slice(0,2).join('.'):'')+' right='+Math.round(r.right));if(wide.length>6)break;}}}
     const hdr=document.querySelector('header,#uhead'); const hr=hdr?hdr.getBoundingClientRect():null; const hpos=hdr?getComputedStyle(hdr).position:'';
     const main=document.querySelector('main,#main'); const mr=main?main.getBoundingClientRect():null;
     const overlapHeader=(hr&&mr&&(hpos==='fixed'||hpos==='sticky')&&mr.top<hr.bottom-1)?('main.top='+Math.round(mr.top)+' < header.bottom='+Math.round(hr.bottom)):'';
     const small=[]; for(const el of document.querySelectorAll('a,button,input,select,[role=button]')){const r=el.getBoundingClientRect();if(r.width===0||r.height===0)continue;if(r.bottom<0||r.top>innerHeight*3)continue;if(r.height<40||r.width<40){small.push(el.tagName.toLowerCase()+(el.className&&typeof el.className==='string'?'.'+el.className.trim().split(/\s+/)[0]:'')+' '+Math.round(r.width)+'x'+Math.round(r.height)+' "'+(el.textContent||el.getAttribute('aria-label')||'').trim().slice(0,20)+'"');}}
     const imgs=[...document.images]; const nodim=imgs.filter(i=>!(i.getAttribute('width')&&i.getAttribute('height'))&&i.getAttribute('src')&&i.width>0).length;
     const broken=imgs.filter(i=>i.complete&&i.naturalWidth===0&&i.getAttribute('src')).map(i=>i.getAttribute('src').slice(0,80));
     // invisible text: low contrast quick check (text color == background or opacity 0)
     let lowc=0; const samp=[...document.querySelectorAll('p,a,span,h1,h2,h3,li,td,label,button')].slice(0,600);
     for(const el of samp){const cs=getComputedStyle(el);if(!el.textContent.trim())continue;if(cs.visibility==='hidden'||cs.display==='none')continue;let bg=cs.backgroundColor,p=el;while((bg==='rgba(0, 0, 0, 0)'||bg==='transparent')&&p.parentElement){p=p.parentElement;bg=getComputedStyle(p).backgroundColor;}
       const L=c=>{const m=c.match(/\d+(\.\d+)?/g);if(!m)return null;const [r,g,b]=m.map(Number).map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});return 0.2126*r+0.7152*g+0.0722*b};
       const l1=L(cs.color),l2=L(bg);if(l1==null||l2==null)continue;const cr=(Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05);if(cr<3)lowc++;}
     const h1=document.querySelectorAll('h1').length; const title=document.title;
     return {vw,sw:de.scrollWidth,over,wide,overlapHeader,hpos,smallN:small.length,small:small.slice(0,10),imgs:imgs.length,nodim,broken:broken.slice(0,5),lowc,h1,title};
   });
   await pg.screenshot({path:`${S}/shots/${vn}-${name}.png`,fullPage:true}).catch(()=>{});
   out.push({vp:vn,name,path,status,reqs:reqs.length,kb:Math.round(bytes/1024),cons:[...new Set(cons)].slice(0,8),failed:[...new Set(failed)].slice(0,8),...m});
   await pg.close();
  }
  await ctx.close();
 }
 await b.close();
 require('fs').writeFileSync(`${S}/chromium.json`,JSON.stringify(out,null,1));
 for(const o of out){console.log(`\n## ${o.vp} ${o.name} ${o.path} status=${o.status} reqs=${o.reqs} weight=${o.kb}KB h1=${o.h1}`);
  console.log(`  overflow=${o.over} (sw ${o.sw}/vw ${o.vw}) headerPos=${o.hpos} overlap=${o.overlapHeader||'-'} imgs=${o.imgs} nodim=${o.nodim} broken=${JSON.stringify(o.broken)} lowContrast=${o.lowc} smallTargets=${o.smallN}`);
  if(o.wide.length)console.log('  wide:',o.wide.join(' | '));
  if(o.small.length)console.log('  small:',o.small.slice(0,6).join(' | '));
  if(o.cons.length)console.log('  console:',o.cons.join('\n    '));
  if(o.failed.length)console.log('  failed:',o.failed.join('\n    '));}
})();
