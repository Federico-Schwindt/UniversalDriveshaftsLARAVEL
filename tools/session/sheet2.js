const {chromium}=require(process.env.PWC);const S=process.argv[2];
(async()=>{const b=await chromium.launch({executablePath:process.env.BX});const p=await b.newPage({viewport:{width:1600,height:900}});
const f=['photos/hero-weld.jpg','photos/band-fabrication.jpg','photos/hero-overland.jpg','photos/panel-custom.jpg','photos/panel-premade.jpg','photos/small-parts.jpg','photos/steel-tube.jpg','photos/tile-complete-driveshafts.jpg','photos/tile-driveshaft-components.jpg','builder/certified-driveshaft.png','home/builder-shot.png','og-default.jpg'];
await p.setContent('<body style="margin:0;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;background:#222;color:#fff;font:13px sans-serif">'+f.map(x=>'<div><img src="https://dev.udshafts.com/img/'+x+'" style="width:100%;height:260px;object-fit:cover"><div>'+x+'</div></div>').join('')+'</body>');
await p.waitForTimeout(3000);await p.screenshot({path:S+'/sheet2.png'});await b.close();})();
