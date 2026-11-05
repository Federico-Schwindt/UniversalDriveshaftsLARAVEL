const {chromium}=require(process.env.PWC);const S=process.argv[2],fs=require('fs');
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1600,height:1000}});
const files=fs.readdirSync(S+'/social-dl').filter(f=>/^p\d+\./.test(f)).sort((a,c)=>parseInt(a.slice(1))-parseInt(c.slice(1)));
const html='<body style="margin:0;display:grid;grid-template-columns:repeat(6,1fr);gap:8px;background:#dde5f3;font:14px sans-serif">'+files.map(f=>{const b64=fs.readFileSync(S+'/social-dl/'+f).toString('base64');const mt=f.endsWith('png')?'png':f.endsWith('webp')?'webp':'jpeg';return '<div style="background:#fff"><img src="data:image/'+mt+';base64,'+b64+'" style="width:100%;height:220px;object-fit:contain"><div>'+f+'</div></div>'}).join('')+'</body>';
await p.setContent(html);await p.waitForTimeout(1500);await p.screenshot({path:S+'/sheet.png'});await b.close();})();
