const { chromium } = require(process.env.PWC); const fs=require('fs');
(async () => {
  const svg = fs.readFileSync(process.env.HOME+'/pti-work/reel/code/inputs/logo.svg','utf8');
  const b = await chromium.launch(); const p = await b.newPage({ viewport:{width:1600,height:300}, deviceScaleFactor:1 });
  await p.setContent('<html><body style="margin:0;background:transparent">'+svg.replace(/width="781" height="125"/,'width="1600" height="256"')+'</body></html>');
  const el = await p.$('svg'); await el.screenshot({ path: process.argv[2], omitBackground: true }); await b.close(); console.log('ok');
})();
