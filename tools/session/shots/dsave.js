const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1400,height:1000}});p.on('pageerror',e=>console.log('PAGEERROR',e.message));
// guest: Save must send to login and come back
await p.goto('https://dev.udshafts.com/driveshaft-designer/1/02Q+03R#A1=52.25&C1=48.5&X2=1.50',{waitUntil:'networkidle'});await p.waitForTimeout(500);
await p.click('#dsg-save'); await p.waitForURL(/\/login/); console.log('guest -> ', p.url());
await p.fill('#l-email','audit-test@uds.local').catch(async()=>p.fill('input[name=email]','audit-test@uds.local')); await p.fill('#l-pw',process.env.UDS_QA_PASSWORD); await Promise.all([p.waitForNavigation(),p.click('form.authform button[type=submit], button:has-text("Sign in")')]);
console.log('after login -> ', p.url());
await p.waitForTimeout(800); const hasDims = await p.evaluate(()=>location.hash);
await p.click('#dsg-save'); await p.waitForTimeout(1200); console.log('save btn:', await p.evaluate(()=>document.getElementById('dsg-save').textContent.trim()), '| hash', hasDims);
await p.goto('https://dev.udshafts.com/account/designs',{waitUntil:'networkidle'}); const rows=await p.evaluate(()=>[...document.querySelectorAll('.mini-row')].map(r=>r.textContent.replace(/\s+/g,' ').trim().slice(0,140))); console.log('designs:', JSON.stringify(rows));
await p.screenshot({path:process.env.SP+'/shots/designs.png',clip:{x:0,y:0,width:1400,height:700}});
const [nav]=await Promise.all([p.waitForNavigation(),p.click('.mini-row a.btn-primary')]); await p.waitForTimeout(600); console.log('open drawing ->', p.url(), '| labels:', await p.evaluate(()=>[...document.querySelectorAll('#dsg-svg .dim-text.is-set')].map(t=>t.getAttribute('name')+'='+t.textContent.trim()).join(',')));
await b.close();})();
