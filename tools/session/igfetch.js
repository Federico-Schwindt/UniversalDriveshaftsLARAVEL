const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,URL,OUT]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const c=await b.newContext({userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'});const p=await c.newPage();
const r=await p.goto(URL,{waitUntil:'load',timeout:40000});const ct=r.headers()['content-type']||'';console.log('status',r.status(),ct);
if(ct.startsWith('image/')){fs.writeFileSync(OUT,await r.body());console.log('saved');}else{const og=await p.$eval('meta[property="og:image"]',e=>e.content).catch(()=>null);console.log('og',og);if(og){const rr=await c.request.get(og);fs.writeFileSync(OUT,await rr.body());console.log('saved og');}}
await b.close();})();
