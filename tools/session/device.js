// node device.js phone|laptop <screenshot> <out.png> [tilt deg]
const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,KIND,SHOT,OUT,TILT]=process.argv;const t=+(TILT||0);
const b64=f=>'data:image/jpeg;base64,'+fs.readFileSync(f).toString('base64');
const phone=`<div class="stage" style="width:1100px;height:1900px">
<div class="dev" style="width:780px;height:1600px;transform:rotateY(${t}deg) rotateX(4deg) rotateZ(${t/6}deg)">
 <div class="btn" style="left:-10px;top:330px;height:110px"></div><div class="btn" style="left:-10px;top:470px;height:170px"></div><div class="btn" style="left:-10px;top:670px;height:170px"></div><div class="btn" style="right:-10px;top:520px;height:250px"></div>
 <div class="frame"><div class="bezel"><div class="screen"><div class="sb"><span>9:41</span><span class="sbi"><i></i><i></i><i></i><i></i><b></b></span></div><img src="${b64(SHOT)}" style="height:calc(100% - 118px)"><div class="glare"></div></div><div class="island"></div></div></div>
</div></div>
<style>.stage{display:grid;place-items:center;perspective:2600px}.dev{position:relative;transform-style:preserve-3d;filter:drop-shadow(0 70px 60px rgba(0,8,30,.55)) drop-shadow(0 18px 18px rgba(0,8,30,.35))}
.frame{position:absolute;inset:0;border-radius:128px;background:linear-gradient(135deg,#7d828c 0%,#2b2f36 18%,#4b5059 45%,#1d2026 70%,#8a8f99 100%);padding:9px;box-shadow:inset 0 0 0 2px rgba(255,255,255,.18)}
.bezel{position:relative;width:100%;height:100%;border-radius:120px;background:#05070b;padding:24px}
.screen{position:relative;width:100%;height:100%;border-radius:98px;overflow:hidden;background:#000}
.screen img{display:block;width:100%;height:100%;object-fit:cover;object-position:top}
.glare{position:absolute;inset:0;background:linear-gradient(118deg,rgba(255,255,255,.16) 0%,rgba(255,255,255,.05) 34%,rgba(255,255,255,0) 35%)}
.sb{height:118px;background:#0b1630;color:#fff;display:flex;align-items:flex-end;justify-content:space-between;padding:0 64px 20px 78px;font:600 34px -apple-system,'Poppins',sans-serif}.sbi{display:flex;align-items:flex-end;gap:5px}.sbi i{display:block;width:8px;background:#fff;border-radius:2px}.sbi i:nth-child(1){height:10px}.sbi i:nth-child(2){height:15px}.sbi i:nth-child(3){height:20px}.sbi i:nth-child(4){height:25px}.sbi b{display:block;width:46px;height:24px;margin-left:14px;border:3px solid #fff;border-radius:7px;background:linear-gradient(90deg,#fff 70%,transparent 70%);background-clip:content-box;padding:2px}
.island{position:absolute;top:52px;left:50%;width:210px;height:62px;margin-left:-105px;border-radius:40px;background:#000}
.btn{position:absolute;width:14px;border-radius:6px;background:linear-gradient(90deg,#3a3e46,#8a8f99,#3a3e46)}</style>`;
const laptop=`<div class="stage" style="width:2200px;height:1500px">
<div class="dev" style="width:1900px;transform:rotateX(${4+t/4}deg) rotateY(${t}deg)">
 <div class="lid"><div class="cam"></div><div class="screen"><img src="${b64(SHOT)}"><div class="glare"></div></div></div>
 <div class="hinge"></div><div class="base"><div class="notch"></div></div>
</div></div>
<style>.stage{display:grid;place-items:center;perspective:3600px}.dev{position:relative;transform-style:preserve-3d;filter:drop-shadow(0 60px 50px rgba(0,8,30,.5))}
.lid{position:relative;margin:0 auto;width:1640px;height:1060px;border-radius:44px 44px 18px 18px;background:#0a0c10;padding:34px 34px 56px;box-shadow:inset 0 0 0 3px #2c3038,0 0 0 4px #a9afb9}
.cam{position:absolute;top:13px;left:50%;width:12px;height:12px;margin-left:-6px;border-radius:50%;background:#1f2a3a}
.screen{position:relative;width:100%;height:100%;border-radius:10px;overflow:hidden;background:#000}
.screen img{display:block;width:100%;height:100%;object-fit:cover;object-position:top}
.glare{position:absolute;inset:0;background:linear-gradient(115deg,rgba(255,255,255,.10) 0%,rgba(255,255,255,.03) 40%,rgba(255,255,255,0) 41%)}
.hinge{margin:0 auto;width:1700px;height:14px;background:linear-gradient(#5d636d,#2e3239)}
.base{position:relative;margin:0 auto;width:1900px;height:46px;border-radius:0 0 40px 40px;background:linear-gradient(#e3e6ea,#b9bec6 55%,#8e949e)}
.notch{position:absolute;top:0;left:50%;width:300px;height:16px;margin-left:-150px;border-radius:0 0 16px 16px;background:linear-gradient(#a8aeb7,#c9cdd3)}</style>`;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:KIND==='phone'?{width:1100,height:1900}:{width:2200,height:1500}});
await p.setContent('<body style="margin:0;background:transparent">'+(KIND==='phone'?phone:laptop)+'</body>');await p.waitForTimeout(800);
const el=await p.$('.dev');const bb=await el.boundingBox();const pad=90;
await p.screenshot({path:OUT,omitBackground:true,clip:{x:Math.max(0,bb.x-pad),y:Math.max(0,bb.y-pad),width:bb.width+pad*2,height:bb.height+pad*2}});await b.close();console.log('ok',OUT);})();
