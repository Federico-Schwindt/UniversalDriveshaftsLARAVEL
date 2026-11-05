const { chromium } = require(process.env.PWC); const fs=require('fs');
(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage();await p.setContent('<html><body></body></html>');
p.on('console',m=>console.log('PAGE',m.text()));
const svg=fs.readFileSync(process.env.F,'utf8');
const r=await p.evaluate(async({svg,S})=>{
  const vb=svg.match(/viewBox="([^"]+)"/)[1].split(/[\s,]+/).map(Number);const W=vb[2],H=vb[3];
  const style='<style>*{stroke:#000!important}.points,.points *{display:none!important}[fill]:not([fill="none"]){fill:#000!important}</style>';
  const src=svg.replace(/(<svg\b[^>]*>)/,'$1'+style).replace(/<svg\b([^>]*)>/,(m,a)=>'<svg'+a.replace(/\swidth="[^"]*"/,'').replace(/\sheight="[^"]*"/,'')+` width="${W*S}" height="${H*S}">`);
  const img=new Image();img.src='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(src);await new Promise((ok,no)=>{img.onload=ok;img.onerror=()=>no(new Error('img'));});
  const cw=W*S,ch=H*S;const c=document.createElement('canvas');c.width=cw;c.height=ch;const g=c.getContext('2d',{willReadFrequently:true});g.fillStyle='#fff';g.fillRect(0,0,cw,ch);g.drawImage(img,0,0,cw,ch);
  const d=g.getImageData(0,0,cw,ch).data;let dark=0;for(let i=0;i<cw*ch;i++)if(d[i*4]<160)dark++;
  const starts=new Map();const push=(x1,y1,x2,y2)=>{const k=x1+','+y1;if(!starts.has(k))starts.set(k,[]);starts.get(k).push([x2,y2]);};
  push(0,0,1,0);push(1,0,1,1);push(1,1,0,1);push(0,1,0,0);
  const loops=[];for(const [k,list] of starts){while(list.length){const [sx,sy]=k.split(',').map(Number);const ring=[[sx,sy]];let nxt=list.pop();let guard=0;
    while(nxt&&!(nxt[0]===sx&&nxt[1]===sy)&&guard++<1e6){ring.push(nxt);const l2=starts.get(nxt[0]+','+nxt[1]);nxt=l2&&l2.length?l2.pop():null;}
    console.log('ring',JSON.stringify(ring),'nxt',JSON.stringify(nxt));if(ring.length>3)loops.push(ring);}}
  return {cw,ch,dark:dark/(cw*ch),loops:loops.length};
},{svg,S:24});console.log(JSON.stringify(r));await b.close();})();
