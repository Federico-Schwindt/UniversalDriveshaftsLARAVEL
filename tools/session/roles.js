const {chromium}=require(process.env.PWC);const S=process.argv[2],PW=require('fs').readFileSync(S+'/qa-pw','utf8').trim();
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const c=await b.newContext({viewport:{width:1440,height:900}});const p=await c.newPage();
p.on('console',m=>{if(m.type()==='error')console.log('console:',m.text())});p.on('pageerror',e=>console.log('pageerror',e.message));
await p.goto('https://dev.udshafts.com/login');await p.fill('#l-email','qa-shot-temp@example.com');await p.fill('#l-pw',PW);
await Promise.all([p.waitForNavigation(),p.click('form.authform button[type=submit]')]);console.log('after login',p.url());
const r=await p.goto('https://dev.udshafts.com/admin/settings?tab=users');console.log('status',r.status(),p.url());
await p.screenshot({path:S+'/roles-1.png'});
const row=p.locator('tr',{hasText:'qa-staff-temp@example.com'});console.log('row count',await row.count());
if(await row.count()){await row.locator('select[name=role]').selectOption('manager');await Promise.all([p.waitForNavigation(),row.locator('button').click()]);
 console.log('after apply',p.url(), (await p.locator('.flash').allInnerTexts()).join(' | '));await p.screenshot({path:S+'/roles-2.png'});}
await b.close();})().catch(e=>{console.error(e);process.exit(1)});
