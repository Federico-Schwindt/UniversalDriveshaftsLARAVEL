// node mockup.js <device-img> <screenshot> <out> <mode: under|over> <seedX%> <seedY%> [tol] [outScale]
// Finds the screen area by flood fill from a seed (transparent pixels for "under", light pixels for "over"),
// fits the screenshot into its 4 corners with a perspective transform, and renders a PNG.
const {chromium}=require(process.env.PWC);const fs=require('fs');
const [,,DEV,SHOT,OUT,MODE,SX,SY,TOL,EXP,RAD]=process.argv;const expand=+(EXP||0),radius=+(RAD||0);
const b64=f=>{const e=f.split('.').pop().toLowerCase();return 'data:image/'+(e==='png'?'png':e==='webp'?'webp':'jpeg')+';base64,'+fs.readFileSync(f).toString('base64');};
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage();
const info=await p.evaluate(async([dev,mode,sx,sy,tol])=>{const img=new Image();img.src=dev;await img.decode();const W=img.naturalWidth,H=img.naturalHeight;
 const c=document.createElement('canvas');c.width=W;c.height=H;const x=c.getContext('2d');x.drawImage(img,0,0);const px=x.getImageData(0,0,W,H).data;
 const ok=i=>mode==='under'?px[i*4+3]<40:(Math.min(px[i*4],px[i*4+1],px[i*4+2])>255-tol);
 const seen=new Uint8Array(W*H),q=[Math.round(sy/100*H)*W+Math.round(sx/100*W)];
 while(q.length){const i=q.pop();if(seen[i]||!ok(i))continue;seen[i]=1;const X=i%W,Y=(i/W)|0;if(X>0)q.push(i-1);if(X<W-1)q.push(i+1);if(Y>0)q.push(i-W);if(Y<H-1)q.push(i+W);}
 let tl=[0,0,1e9],tr=[0,0,-1e9],bl=[0,0,-1e9],br=[0,0,-1e9];let n=0;
 for(let i=0;i<W*H;i++)if(seen[i]){n++;const X=i%W,Y=(i/W)|0;if(X+Y<tl[2])tl=[X,Y,X+Y];if(X-Y>tr[2])tr=[X,Y,X-Y];if(Y-X>bl[2])bl=[X,Y,Y-X];if(X+Y>br[2])br=[X,Y,X+Y];}
 return {W,H,n,q:[tl,tr,br,bl].map(a=>[a[0],a[1]])};},[b64(DEV),MODE,+SX,+SY,+(TOL||30)]);
console.log('screen px',info.n,'corners',JSON.stringify(info.q));
{const cx=info.q.reduce((s,p)=>s+p[0],0)/4,cy=info.q.reduce((s,p)=>s+p[1],0)/4;info.q=info.q.map(([x,y])=>{const dx=x-cx,dy=y-cy,l=Math.hypot(dx,dy);return [x+dx/l*expand*1.41,y+dy/l*expand*1.41];});}
// homography rect(0,0,w,h) -> quad as CSS matrix3d
const sw=1000,sh=Math.round(1000*Math.hypot(info.q[3][0]-info.q[0][0],info.q[3][1]-info.q[0][1])/Math.hypot(info.q[1][0]-info.q[0][0],info.q[1][1]-info.q[0][1]));
function solve(A,bb){const n=bb.length;for(let i=0;i<n;i++){let m=i;for(let k=i+1;k<n;k++)if(Math.abs(A[k][i])>Math.abs(A[m][i]))m=k;[A[i],A[m]]=[A[m],A[i]];[bb[i],bb[m]]=[bb[m],bb[i]];for(let k=i+1;k<n;k++){const f=A[k][i]/A[i][i];for(let j=i;j<n;j++)A[k][j]-=f*A[i][j];bb[k]-=f*bb[i];}}const xx=new Array(n);for(let i=n-1;i>=0;i--){let s=bb[i];for(let j=i+1;j<n;j++)s-=A[i][j]*xx[j];xx[i]=s/A[i][i];}return xx;}
const src=[[0,0],[sw,0],[sw,sh],[0,sh]],dst=info.q;const A=[],B=[];
for(let i=0;i<4;i++){const [x,y]=src[i],[u,v]=dst[i];A.push([x,y,1,0,0,0,-u*x,-u*y]);B.push(u);A.push([0,0,0,x,y,1,-v*x,-v*y]);B.push(v);}
const h=solve(A,B);const m=[h[0],h[3],0,h[6],h[1],h[4],0,h[7],0,0,1,0,h[2],h[5],0,1];
const rounded=await p.evaluate(async([d,w,h,r])=>{const im=new Image();im.src=d;await im.decode();const c=document.createElement('canvas');c.width=w;c.height=h;const x=c.getContext('2d');x.beginPath();x.roundRect(0,0,w,h,r);x.clip();const k=Math.max(w/im.naturalWidth,h/im.naturalHeight);x.drawImage(im,0,0,im.naturalWidth*k,im.naturalHeight*k);const g=x.createLinearGradient(0,0,w,h);g.addColorStop(0,'rgba(255,255,255,.10)');g.addColorStop(.38,'rgba(255,255,255,.03)');g.addColorStop(.40,'rgba(255,255,255,0)');x.fillStyle=g;x.fillRect(0,0,w,h);return c.toDataURL('image/png');},[b64(SHOT),sw,sh,radius]);
const shot=`<img src="${rounded}" style="position:absolute;left:0;top:0;width:${sw}px;height:${sh}px;transform-origin:0 0;transform:matrix3d(${m.join(',')})">`;
const dev=`<img src="${b64(DEV)}" style="position:absolute;left:0;top:0;width:${info.W}px;height:${info.H}px">`;
await p.setViewportSize({width:info.W,height:info.H});
await p.setContent(`<body style="margin:0;background:transparent"><div style="position:relative;width:${info.W}px;height:${info.H}px;overflow:hidden">${MODE==='under'?shot+dev:dev+shot}</div></body>`);
await p.waitForTimeout(800);await p.screenshot({path:OUT,omitBackground:true,type:'png'});await b.close();console.log('ok',OUT);})();
