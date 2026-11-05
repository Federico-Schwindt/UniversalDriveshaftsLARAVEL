const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1400,height:900}});await p.goto('https://dev.udshafts.com/register?x='+Date.now(),{waitUntil:'networkidle'});
const st=async()=>p.evaluate(()=>{const i=document.querySelector('#r-phone');const w=i.closest('.tel');return i.value+' | '+w.querySelector('select').value+' | '+w.querySelector('input[type=hidden]').value;});
const el=await p.$('#r-phone');
for (const t of ['18135246491','+5','+52 55 1234 5678']){ await el.fill(''); await p.keyboard.type(t); await p.waitForTimeout(80); console.log(JSON.stringify(t),'->',await st()); }
await el.fill(''); await p.selectOption('.tel__cc','MX'); await p.keyboard.type('5512345678'); console.log('select MX then type ->',await st());
await p.selectOption('.tel__cc','US'); await el.fill(''); await p.keyboard.type('81352'); console.log('short US valid?', await p.evaluate(()=>document.querySelector('#r-phone').checkValidity()), await st());
await b.close();})();
