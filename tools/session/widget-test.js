const {chromium}=require(process.env.PWC);
const S=process.argv[2], PW=require('fs').readFileSync(S+'/qa-pw','utf8').trim();
(async()=>{const b=await chromium.launch({executablePath:process.env.BX,args:['--autoplay-policy=no-user-gesture-required']});
const c=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true});const p=await c.newPage();
p.on('console',m=>{if(m.type()==='error')console.log('console:',m.text())});
p.on('pageerror',e=>console.log('pageerror',e.message));
await p.goto('https://dev.udshafts.com/login',{waitUntil:'load'});
await p.fill('#l-email','qa-shot-temp@example.com');await p.fill('#l-pw',PW);
await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('form.authform button[type=submit]')]);
await p.goto('https://dev.udshafts.com/',{waitUntil:'load'});await p.waitForTimeout(1000);
await p.click('.ua-launch');await p.waitForTimeout(1500);
async function ask(t){const n=await p.$$eval('.ua-row--bot',e=>e.length);const t0=Date.now();
  await p.fill('[data-ua-input]',t);await p.click('[data-ua-send]');
  await p.waitForFunction(n=>document.querySelectorAll('.ua-row--bot').length>n && !document.querySelector('.ua-typing'),n,{timeout:120000});
  await p.waitForTimeout(900); const last=await p.$$('.ua-row--bot'); const m=last[last.length-1];
  console.log('Q:',t,'| '+((Date.now()-t0)/1000).toFixed(1)+'s | voice:',await m.$('.ua-msg--voice')!==null,'| text:',(await m.innerText()).replace(/\s+/g,' ').slice(0,160));
  console.log('   widgets:',await p.$$eval('.ua-log > .ua-w, .ua-log > .ua-quick',e=>e.map(x=>x.className)));}
await ask('Where is order QA-261028-TRK1?');
const t2=await p.$('.ua-w-trk'); if(t2){await t2.scrollIntoViewIfNeeded(); await t2.screenshot({path:S+'/w5-shipped.png'});}
await ask('can you check what time you close on saturday?');
await (await p.$('.ua-panel')).screenshot({path:S+'/w2-orders.png'});
const ord=await p.$('.ua-w-orders .ua-ord:not(:disabled)');
if(ord){const n=await p.$$eval('.ua-row--bot',e=>e.length); await ord.click();
  await p.waitForFunction(n=>document.querySelectorAll('.ua-row--bot').length>n && !document.querySelector('.ua-typing'),n,{timeout:120000}); await p.waitForTimeout(900);
  const last=await p.$$('.ua-row--bot'); console.log('tap order → voice:',await last[last.length-1].$('.ua-msg--voice')!==null,(await last[last.length-1].innerText()).replace(/\s+/g,' ').slice(0,160));
  console.log('   widgets:',await p.$$eval('.ua-log > .ua-w',e=>e.map(x=>x.className)));}
const trk=await p.$('.ua-w-trk'); if(trk){await trk.scrollIntoViewIfNeeded(); await trk.screenshot({path:S+'/w3-tracking.png'});}
await (await p.$('.ua-panel')).screenshot({path:S+'/w3-panel.png'});
await p.click('[data-ua-hist]');await p.waitForTimeout(1500);
await (await p.$('.ua-panel')).screenshot({path:S+'/w4-history.png'});
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
