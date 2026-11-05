const { chromium } = require(process.env.PWC);(async()=>{const b=await chromium.launch({headless:true});
for (const [path,name,w] of [['/register','register',1400],['/login','login',1400],['/forgot-password','forgot',1400],['/register','register-m',390],['/login','login-m',390]]){const p=await b.newPage({viewport:{width:w,height:900}});await p.goto('https://dev.udshafts.com'+path+'?nocache='+Date.now(),{waitUntil:'networkidle'});await p.waitForTimeout(300);await p.screenshot({path:process.env.SP+'/shots/'+name+'.png',fullPage:true});await p.close();}
await b.close();console.log('ok');})();
