const {chromium}=require(process.env.PWC);const fs=require('fs');const pairs=process.argv.slice(2);
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage();
for(const pr of pairs){const [i,o]=pr.split('=>');const e=i.split('.').pop().toLowerCase();const d='data:image/'+(e==='png'?'png':e==='webp'?'webp':'jpeg')+';base64,'+fs.readFileSync(i).toString('base64');
const r=await p.evaluate(async([d,png])=>{const im=new Image();im.src=d;await im.decode();const k=Math.min(1,1800/Math.max(im.naturalWidth,im.naturalHeight));const c=document.createElement('canvas');c.width=Math.round(im.naturalWidth*k);c.height=Math.round(im.naturalHeight*k);c.getContext('2d').drawImage(im,0,0,c.width,c.height);return c.toDataURL(png?'image/png':'image/jpeg',.88);},[d,o.endsWith('.png')]);
fs.writeFileSync(o,Buffer.from(r.split(',')[1],'base64'));console.log(o.split('/').pop(),fs.statSync(o).size);}
await b.close();})();
