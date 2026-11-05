const {chromium}=require(process.env.PWC);const fs=require('fs');const S=process.argv[2];const names=process.argv.slice(3);
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:names.length*540+ (names.length-1)*16,height:960}});
await p.setContent('<body style="margin:0;display:flex;gap:16px;background:#888;align-items:flex-start">'+names.map(n=>'<img src="data:image/png;base64,'+fs.readFileSync(S+'/social-prev/'+n+'.png').toString('base64')+'" style="width:540px">').join('')+'</body>');
await p.waitForTimeout(500);await p.screenshot({path:S+'/social-prev/_m.png',fullPage:true});await b.close();})();
