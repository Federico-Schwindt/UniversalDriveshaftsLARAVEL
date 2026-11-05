const {chromium}=require(process.env.PWC);
const S=process.argv[2], tag=process.argv[3]||'before', PW=require('fs').readFileSync(S+'/qa-pw','utf8').trim();
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
const c=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true});const p=await c.newPage();
await p.goto('https://dev.udshafts.com/login',{waitUntil:'load'});
await p.fill('#l-email','qa-shot-temp@example.com');await p.fill('#l-pw',PW);
await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('form.authform button[type=submit]')]);
for(const pg of ['account','account/orders','account/settings','account/addresses','account/business']){
  await p.goto('https://dev.udshafts.com/'+pg,{waitUntil:'load'});await p.waitForTimeout(1200);
  const w=await p.evaluate(()=>[document.documentElement.scrollWidth,innerWidth]);
  console.log(pg,'scrollW/innerW',w);
  await p.screenshot({path:`${S}/m-${tag}-${pg.replace(/\//g,'_')}.png`,fullPage:false});
  if(pg==='account') await p.screenshot({path:`${S}/m-${tag}-account-full.png`,fullPage:true});
}
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
