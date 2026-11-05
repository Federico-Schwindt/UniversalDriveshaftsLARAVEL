const {chromium}=require(process.env.PWC);
const S=process.argv[2], PW=require('fs').readFileSync(S+'/qa-pw','utf8').trim();
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
const c=await b.newContext({viewport:{width:1280,height:900}});const p=await c.newPage();
await p.goto('https://dev.udshafts.com/login',{waitUntil:'load'});
await p.fill('#l-email','qa-shot-temp@example.com');await p.fill('#l-pw',PW);
await Promise.all([p.waitForNavigation({waitUntil:'load'}),p.click('form.authform button[type=submit]')]);
console.log('after login',p.url());
await p.goto('https://dev.udshafts.com/account/settings',{waitUntil:'load'});await p.waitForTimeout(2500);
const el=await p.$('#avatar');await p.evaluate(()=>window.scrollTo(0,0));
await p.screenshot({path:S+'/sh-settings.png',fullPage:false});
if(el) await el.screenshot({path:S+'/sh-avatar.png'});
const cards=await p.$('#cards'); if(cards) await cards.screenshot({path:S+'/sh-cards.png'});
// chat
await p.goto('https://dev.udshafts.com/',{waitUntil:'load'});await p.waitForTimeout(1500);
await p.click('.ua-launch');await p.waitForTimeout(1500);
await p.fill('[data-ua-input]','hi');await p.click('[data-ua-send]');
await p.waitForSelector('.ua-row--bot',{timeout:90000});await p.waitForTimeout(800);
console.log('me rows',await p.$$eval('.ua-row--me',e=>e.length),'bot rows',await p.$$eval('.ua-row--bot',e=>e.length));
await (await p.$('.ua-panel')).screenshot({path:S+'/sh-chat.png'});
// checkout with a part
await p.goto('https://dev.udshafts.com/parts/5-1310XU',{waitUntil:'load'});
await p.evaluate(()=>fetch('/cart/add',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'product_id=11383&quantity=1'}));
await p.goto('https://dev.udshafts.com/checkout',{waitUntil:'load'});await p.waitForTimeout(4000);
const pay=await p.$('#pay-card-box'); if(pay){await pay.scrollIntoViewIfNeeded(); await pay.screenshot({path:S+'/sh-pay.png'});}
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
