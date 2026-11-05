const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});const p=await b.newPage({viewport:{width:1440,height:900}});
await p.goto('https://dev.udshafts.com/',{waitUntil:'networkidle'});
const out=await p.evaluate(async()=>{const head=document.querySelector('.uhead');const res=[];for(const y of [0,4,8,10,12,20,30,36,38,40,44,50,60,80,120]){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,60));const r=head.getBoundingClientRect();res.push(`${y}: top=${Math.round(r.top)} h=${Math.round(r.height)} stuck=${head.classList.contains('is-stuck')} bodyH=${document.body.scrollHeight} promoH=${Math.round(document.querySelector('.upromo').getBoundingClientRect().height)}`);}return res;});
console.log(out.join('\n'));
// count class flips during a slow scroll from 0 to 120
const flips=await p.evaluate(async()=>{const head=document.querySelector('.uhead');let last=head.classList.contains('is-stuck'),flips=0;for(let y=0;y<=120;y+=2){window.scrollTo(0,y);await new Promise(r=>requestAnimationFrame(r));const now=head.classList.contains('is-stuck');if(now!==last){flips++;last=now;}}return flips;});
console.log('class flips during slow scroll 0->120:',flips);await b.close();})();
