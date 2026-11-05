const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1400,height:1000}});
await p.goto('https://dev.udshafts.com/driveshaft-designer/1/02Q+03R#A1=52.25&X2=1.50',{waitUntil:'networkidle'});await p.waitForTimeout(800);
const info=await p.evaluate(()=>{const ls=[...document.querySelectorAll('#dsg-svg .dim-text')].map(t=>t.getAttribute('name')+':'+t.textContent.trim());const f=document.querySelector('#dsg-svg text.front');return {labels:ls,front:f&&f.getAttribute('x'),rows:[...document.querySelectorAll('[data-dim]')].map(r=>r.dataset.dim)};});
console.log(JSON.stringify(info));
// click Z2 and type a value
const z=await p.$('#dsg-svg [name="Z2"]'); await z.click(); await p.waitForTimeout(200); await p.keyboard.type('3.75'); await p.keyboard.press('Enter'); await p.waitForTimeout(300);
console.log('after edit', await p.evaluate(()=>location.hash+' | Z2='+document.querySelector('#dsg-svg [name="Z2"]').textContent.trim()));
const box=await (await p.$('#dsg-svg')).boundingBox(); await p.screenshot({path:process.env.SP+'/shots/dsg-02Q.png',clip:{x:box.x,y:box.y,width:box.width,height:Math.min(box.height,700)}});
await b.close();})();
