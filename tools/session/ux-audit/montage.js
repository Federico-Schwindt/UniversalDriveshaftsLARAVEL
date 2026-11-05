const {chromium}=require(process.env.PWC);const fs=require('fs');
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});
const [glob,out,w]=process.argv.slice(2);const files=glob.split(',');
const html='<body style="margin:0;display:flex;flex-wrap:wrap;background:#888">'+files.map(f=>`<figure style="margin:2px;width:${w}px"><figcaption style="font:12px sans-serif;background:#ff0">${f}</figcaption><img style="width:100%" src="file://${__dirname}/shots/${f}"></figure>`).join('')+'</body>';
fs.writeFileSync(__dirname+'/m.html',html);const p=await b.newPage({viewport:{width:w*4+20,height:800}});await p.goto('file://'+__dirname+'/m.html');await p.waitForTimeout(800);await p.screenshot({path:__dirname+'/shots/'+out,fullPage:true});await b.close()})();
