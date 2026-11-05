const { chromium } = require(process.env.PWC); const fs=require('fs');
(async()=>{const b=await chromium.launch({headless:true}); const svg=fs.readFileSync(process.env.ROOT+'/public/favicon.svg','utf8');
for (const s of [16,32,48,180,192,512]){const p=await b.newPage({viewport:{width:s,height:s},deviceScaleFactor:1});
  await p.setContent('<html><body style="margin:0;background:transparent">'+svg.replace(/width="100" height="100"/,`width="${s}" height="${s}"`)+'</body></html>');
  await p.screenshot({path:process.env.ROOT+`/public/_fav-${s}.png`,omitBackground:true}); await p.close();}
await b.close(); console.log('rendered');})();
