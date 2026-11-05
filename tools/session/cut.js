const {chromium}=require(process.env.PWC);const S=process.argv[2];
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1500,height:500}});
const imgs=['img/hero/hero-ujoint.png','img/home/premade-shaft.png','img/builder/certified-driveshaft.png','img/makes/ford.png'].map(u=>'https://dev.udshafts.com/'+u);
await p.setContent('<body style="margin:0;background:#1246ab;display:flex;gap:20px;align-items:center">'+imgs.map(u=>'<img src="'+u+'" style="height:420px;max-width:360px;object-fit:contain">').join('')+'</body>');
await p.waitForTimeout(2500);await p.screenshot({path:S+'/cutouts.png'});await b.close();})();
