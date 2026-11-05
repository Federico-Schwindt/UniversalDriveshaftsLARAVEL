// node cutout.js <in> <out.png> [tol] — remove a white/light background by flood fill from the borders, feather, trim
const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,IN,OUT,TOL]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage();
const e=IN.split('.').pop().toLowerCase();const mt=e==='png'?'png':e==='webp'?'webp':'jpeg';
const data='data:image/'+mt+';base64,'+fs.readFileSync(IN).toString('base64');
const res=await p.evaluate(async([data,tol])=>{const img=new Image();img.src=data;await img.decode();
 const W=img.naturalWidth,H=img.naturalHeight,c=document.createElement('canvas');c.width=W;c.height=H;const x=c.getContext('2d');x.drawImage(img,0,0);
 const d=x.getImageData(0,0,W,H),px=d.data,seen=new Uint8Array(W*H),q=[];
 const isBg=i=>{const r=px[i*4],g=px[i*4+1],b=px[i*4+2],a=px[i*4+3];if(a<20)return true;const mn=Math.min(r,g,b),mx=Math.max(r,g,b);return mn>255-tol&&mx-mn<28;};
 for(let X=0;X<W;X++){q.push(X,(H-1)*W+X);}for(let Y=0;Y<H;Y++){q.push(Y*W,Y*W+W-1);}
 while(q.length){const i=q.pop();if(seen[i])continue;if(!isBg(i))continue;seen[i]=1;const X=i%W,Y=(i/W)|0;if(X>0)q.push(i-1);if(X<W-1)q.push(i+1);if(Y>0)q.push(i-W);if(Y<H-1)q.push(i+W);}
 for(let pass=0;pass<3;pass++){const add=[];for(let Y=1;Y<H-1;Y++)for(let X=1;X<W-1;X++){const i=Y*W+X;if(seen[i])continue;if(!(seen[i-1]||seen[i+1]||seen[i-W]||seen[i+W]))continue;const r=px[i*4],g=px[i*4+1],b=px[i*4+2];if(Math.min(r,g,b)>185&&Math.max(r,g,b)-Math.min(r,g,b)<40)add.push(i);}for(const i of add)seen[i]=1;}
 for(let i=0;i<W*H;i++)if(seen[i])px[i*4+3]=0;
 // feather: edge pixels next to background get partial alpha by lightness
 for(let Y=1;Y<H-1;Y++)for(let X=1;X<W-1;X++){const i=Y*W+X;if(seen[i])continue;if(seen[i-1]||seen[i+1]||seen[i-W]||seen[i+W]){const l=(px[i*4]+px[i*4+1]+px[i*4+2])/3;px[i*4+3]=Math.max(40,Math.min(255,(255-l)*4+60));}}
 x.putImageData(d,0,0);
 let x0=W,y0=H,x1=0,y1=0;for(let Y=0;Y<H;Y++)for(let X=0;X<W;X++)if(px[(Y*W+X)*4+3]>30){if(X<x0)x0=X;if(X>x1)x1=X;if(Y<y0)y0=Y;if(Y>y1)y1=Y;}
 const pad=6,o=document.createElement('canvas');o.width=x1-x0+pad*2;o.height=y1-y0+pad*2;o.getContext('2d').drawImage(c,x0-pad,y0-pad,o.width,o.height,0,0,o.width,o.height);
 return o.toDataURL('image/png');},[data,parseInt(TOL||'22')]);
fs.writeFileSync(OUT,Buffer.from(res.split(',')[1],'base64'));await b.close();console.log('ok',OUT);})();
