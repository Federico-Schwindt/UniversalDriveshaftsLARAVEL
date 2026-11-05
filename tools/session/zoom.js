const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,IN,OUT,x,y,w,h]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:+w,height:+h}});
await p.setContent('<body style="margin:0;background:#1d5fe8;overflow:hidden"><img src="data:image/png;base64,'+fs.readFileSync(IN).toString('base64')+'" style="position:absolute;left:-'+x+'px;top:-'+y+'px">');await p.waitForTimeout(500);await p.screenshot({path:OUT});await b.close();})();
