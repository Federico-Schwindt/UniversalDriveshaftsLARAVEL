const {chromium}=require(process.env.PWC);const [,,URL,OUT,W,H]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:+W,height:+H},deviceScaleFactor:2});await p.goto(URL,{waitUntil:'networkidle',timeout:60000});await p.waitForTimeout(1500);await p.screenshot({path:OUT});await b.close();console.log('ok',OUT);})();
