// node maskapply.js <original> <canva-thumb.png> <out.png> — use Canva's alpha (thumb) as a mask on the full-res original
const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,ORIG,THUMB,OUT]=process.argv;
const b64=f=>{const e=f.split('.').pop().toLowerCase();return 'data:image/'+(e==='png'?'png':e==='webp'?'webp':'jpeg')+';base64,'+fs.readFileSync(f).toString('base64')};
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage();
const out=await p.evaluate(async([o,t])=>{const L=s=>new Promise(r=>{const i=new Image();i.onload=()=>r(i);i.src=s});const oi=await L(o),ti=await L(t);
const W=oi.naturalWidth,H=oi.naturalHeight;
// Canva thumb is the subject's bounding box, contain-fit in 200px; we need its placement in the original: estimate by scanning the thumb alpha bbox and the original... simpler: the thumb keeps the original aspect (full frame scaled). Scale it to W×H.
const m=document.createElement('canvas');m.width=W;m.height=H;const mx=m.getContext('2d');mx.imageSmoothingEnabled=true;mx.imageSmoothingQuality='high';mx.filter='blur(0.6px)';mx.drawImage(ti,0,0,W,H);
const md=mx.getImageData(0,0,W,H).data;
const c=document.createElement('canvas');c.width=W;c.height=H;const x=c.getContext('2d');x.drawImage(oi,0,0);const d=x.getImageData(0,0,W,H);
for(let i=0;i<W*H;i++){d.data[i*4+3]=md[i*4+3];}
x.putImageData(d,0,0);return c.toDataURL('image/png');},[b64(ORIG),b64(THUMB)]);
fs.writeFileSync(OUT,Buffer.from(out.split(',')[1],'base64'));await b.close();console.log('ok',OUT);})();
