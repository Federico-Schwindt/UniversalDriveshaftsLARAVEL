// node imgsearch.js <outdir> <tag> <query> [n] — search images in a real browser (Bing), download the top results
const {chromium}=require(process.env.PWC);const fs=require('fs');
const [,,D,T,Q,N]=process.argv;const n=parseInt(N||'10');
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const c=await b.newContext({userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',locale:'en-US',viewport:{width:1400,height:1000}});const p=await c.newPage();
await p.goto('https://www.bing.com/images/search?q='+encodeURIComponent(Q)+'&qft='+(process.env.QFT||'+filterui:imagesize-large')+'',{waitUntil:'domcontentloaded',timeout:40000});
await p.waitForTimeout(2500);for(let i=0;i<3;i++){await p.mouse.wheel(0,3000);await p.waitForTimeout(800);}
const items=await p.$$eval('a.iusc',as=>as.map(a=>{try{const m=JSON.parse(a.getAttribute('m'));return {u:m.murl,t:m.t};}catch(e){return null}}).filter(Boolean));
fs.mkdirSync(D,{recursive:true});let k=0;const log=[];
const BAD=/freepik|magnific|shutterstock|istock|gettyimages|alamy|dreamstime|adobe|depositphotos|123rf|vecteezy|canstock|bigstock|pond5|stocksy|agefoto|masterfile|lovepik|pngtree|pikbest|wallpaperflare/i;
for(const it of items){if(k>=n)break;if(BAD.test(it.u))continue;try{const r=await c.request.get(it.u,{timeout:20000});if(!r.ok())continue;const buf=await r.body();if(buf.length<20000)continue;const ct=(r.headers()['content-type']||'');const ext=ct.includes('png')?'png':ct.includes('webp')?'webp':'jpg';k++;fs.writeFileSync(`${D}/${T}-${k}.${ext}`,buf);log.push(`${T}-${k}.${ext} | ${it.t} | ${it.u}`);}catch(e){}}
fs.writeFileSync(`${D}/${T}.log`,log.join('\n'));console.log(T,items.length,'found',k,'saved; first:',(items[0]||{}).t);await b.close();})();
