const {chromium}=require(process.env.PWC);const fs=require('fs');const [,,D,OUT]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1800,height:700}});
const files=fs.readdirSync(D).filter(f=>/\.(jpe?g|png|webp)$/i.test(f)).sort();
await p.setContent('<body style="margin:0;display:grid;grid-template-columns:repeat(5,1fr);gap:6px;background:#8a93a6;font:13px sans-serif">'+files.map(f=>{const e=f.split('.').pop().toLowerCase();return '<div style="background:repeating-conic-gradient(#ddd 0 25%,#fff 0 50%) 0/20px 20px"><img src="data:image/'+(e==='png'?'png':e==='webp'?'webp':'jpeg')+';base64,'+fs.readFileSync(D+'/'+f).toString('base64')+'" style="width:100%;height:300px;object-fit:contain;display:block"><div style="background:#000;color:#fff;padding:2px 4px">'+f+'</div></div>'}).join('')+'</body>');
await p.waitForTimeout(1500);await p.screenshot({path:OUT,fullPage:true});await b.close();})();
