const {chromium}=require(process.env.PWC);const [,,URL,OUT]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1440,height:900},deviceScaleFactor:1});await p.goto(URL,{waitUntil:'networkidle',timeout:60000});
const h=await p.evaluate(()=>document.documentElement.scrollHeight);await p.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));await p.waitForTimeout(800);
await p.screenshot({path:OUT+'-foot.png'});
const info=await p.evaluate(()=>{const f=document.querySelector('footer');const r=f?f.getBoundingClientRect():null;const cs=f?getComputedStyle(f):null;const body=getComputedStyle(document.body);const html=getComputedStyle(document.documentElement);const after=f?[...f.parentElement.children].slice([...f.parentElement.children].indexOf(f)+1).map(e=>e.tagName+'.'+e.className+' h='+e.getBoundingClientRect().height):[];return {footerBottom:r&&r.bottom,docH:document.documentElement.scrollHeight,vh:innerHeight,footerBg:cs&&cs.backgroundColor,bodyBg:body.backgroundColor,htmlBg:html.backgroundColor,after,footerMB:cs&&cs.marginBottom,bodyPB:body.paddingBottom};});
console.log(JSON.stringify(info));
// language switch hover
const ls=await p.$('.lang-switch, [class*=lang]');if(ls){await ls.scrollIntoViewIfNeeded();await p.evaluate(()=>window.scrollTo(0,0));await p.waitForTimeout(300);const bb=await ls.boundingBox();await p.mouse.move(bb.x+bb.width/2,bb.y+bb.height/2);await p.waitForTimeout(500);await p.screenshot({path:OUT+'-lang.png',clip:{x:Math.max(0,bb.x-200),y:Math.max(0,bb.y-20),width:Math.min(1440,bb.width+400),height:bb.height+120}});const cls=await ls.getAttribute('class');console.log('lang',cls,JSON.stringify(bb));}
await b.close();})();
