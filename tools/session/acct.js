const { chromium } = require(process.env.PWC);
const fs=require('fs');
(async()=>{
  const b=await chromium.launch({headless:true});
  const ctx=await b.newContext({viewport:{width:1280,height:900}});
  try{ ctx.addCookies(JSON.parse(fs.readFileSync('cookies.json','utf8'))); }catch(e){}
  const p=await ctx.newPage();
  for (const u of ['/account','/account/orders']) {
    const r=await p.goto('https://dev.udshafts.com'+u,{waitUntil:'domcontentloaded'});
    const title=await p.title();
    const body=(await p.evaluate(()=>document.body.innerText)).slice(0,120).replace(/\n+/g,' | ');
    console.log(u, '→', r.status(), '|', title.slice(0,40), '|', body);
  }
  await b.close();
})();
